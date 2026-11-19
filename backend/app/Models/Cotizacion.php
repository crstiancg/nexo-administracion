<?php

namespace App\Models;

use App\Support\Igv;
use App\Support\Fechas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * pendiente → convertida (en un pedido o en una orden de servicio) | rechazada. Una pendiente con
 * `valida_hasta` pasada se muestra como vencida. Los cambios de estado pasan
 * por App\Services\Cotizaciones.
 */
#[Fillable(['tipo', 'mostrar_precios', 'equipos', 'ganancia', 'ganancia_tipo', 'cliente_id', 'valida_hasta', 'forma_pago', 'entrega', 'garantia', 'condiciones', 'observacion', 'descuento'])]
class Cotizacion extends Model
{
    public const PENDIENTE = 'pendiente';

    public const CONVERTIDA = 'convertida';

    public const RECHAZADA = 'rechazada';

    /** normal: ítems sueltos. pc: los componentes de una computadora armada. */
    public const TIPOS = ['normal', 'pc'];

    /** Tope de "N° de equipos" de una PC armada. */
    public const MAX_EQUIPOS = 999;

    /** Días de validez por defecto. */
    public const VALIDEZ_DIAS = 15;

    /**
     * Explícita: por convención Laravel pluraliza en inglés ("cotizacions").
     */
    protected $table = 'cotizaciones';

    /**
     * El desglose del IGV sigue al total, se guarde por donde se guarde.
     */
    protected static function booted(): void
    {
        static::saving(function (self $documento) {
            if ($documento->isDirty('total') || ! $documento->exists) {
                $documento->forceFill(Igv::desglosar($documento->total));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'valida_hasta' => 'date',
            'vista_at' => 'datetime',
            'mostrar_precios' => 'boolean',
            'equipos' => 'integer',
            'ganancia' => 'decimal:2',
            'ganancia_equipo' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
            'op_gravada' => 'decimal:2',
            'igv' => 'decimal:2',
        ];
    }

    /**
     * Cuántas veces se multiplica cada ítem: en una PC armada los ítems son
     * los de UN equipo; en una normal, siempre 1.
     */
    public function multiplicador(): int
    {
        return $this->tipo === 'pc' ? max(1, (int) $this->equipos) : 1;
    }

    /**
     * La ganancia de UN equipo en soles, sobre su costo (lo que suman los
     * ítems): el monto tal cual o el % del costo. Sólo en una PC armada.
     */
    public static function gananciaPorEquipo(?string $tipo, float $costoEquipo, float $ganancia, ?string $gananciaTipo): float
    {
        if ($tipo !== 'pc' || $ganancia <= 0) {
            return 0.0;
        }

        return $gananciaTipo === 'porcentaje'
            ? round($costoEquipo * $ganancia / 100, 2)
            : round($ganancia, 2);
    }

    /**
     * El precio que ve el cliente de cada ítem: la ganancia del equipo
     * repartida en proporción a su costo, así la proforma (y el pedido) nunca
     * muestran el precio de llegada y las líneas suman el precio del equipo.
     * Lo que sobra o falta por el redondeo va a una línea de cantidad 1 (o,
     * si no hay, a la más cara). Sin ganancia, los precios tal cual.
     *
     * Usa los `items` cargados (todos: también los libres, que llevan su parte).
     *
     * @return array<int, array{precio_unitario: string, subtotal: string}> por id de ítem
     */
    public function preciosDeVenta(): array
    {
        $dinero = fn (float $valor) => number_format($valor, 2, '.', '');
        $costo = round($this->items->sum(fn ($item) => (float) $item->subtotal), 2);
        $ganancia = (float) $this->ganancia_equipo;

        if ($ganancia <= 0 || $costo <= 0) {
            return $this->items->mapWithKeys(fn ($item) => [$item->id => [
                'precio_unitario' => $dinero((float) $item->precio_unitario),
                'subtotal' => $dinero((float) $item->subtotal),
            ]])->all();
        }

        $factor = ($costo + $ganancia) / $costo;
        $filas = [];
        foreach ($this->items as $item) {
            $precio = round((float) $item->precio_unitario * $factor, 2);
            $filas[$item->id] = ['cantidad' => (float) $item->cantidad, 'precio' => $precio, 'subtotal' => round((float) $item->cantidad * $precio, 2)];
        }

        $resto = round($costo + $ganancia - array_sum(array_column($filas, 'subtotal')), 2);
        if ($resto != 0.0) {
            $unitarias = array_filter($filas, fn ($fila) => $fila['cantidad'] == 1.0);
            $candidatas = $unitarias ?: $filas;
            uasort($candidatas, fn ($a, $b) => $b['subtotal'] <=> $a['subtotal']);
            $id = array_key_first($candidatas);
            $filas[$id]['subtotal'] = round($filas[$id]['subtotal'] + $resto, 2);
            $filas[$id]['precio'] = round($filas[$id]['subtotal'] / $filas[$id]['cantidad'], 2);
        }

        return array_map(fn ($fila) => [
            'precio_unitario' => $dinero($fila['precio']),
            'subtotal' => $dinero($fila['subtotal']),
        ], $filas);
    }

    public function vencida(): bool
    {
        return $this->estado === self::PENDIENTE && $this->valida_hasta->lt(Fechas::hoy());
    }

    /** pendiente | vencida | convertida | rechazada */
    public function estadoVisible(): string
    {
        return $this->vencida() ? 'vencida' : $this->estado;
    }

    public function editable(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CotizacionItem::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function ordenServicio(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
