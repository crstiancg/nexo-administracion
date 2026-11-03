<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

/**
 * Una línea del libro de inventario. INMUTABLE en lo que mueve stock: se crea
 * y nunca se borra, y una cantidad mal cargada se corrige con otro movimiento
 * (una salida que compense una entrada de más): así el historial siempre
 * explica el stock. Lo único editable son los datos de una entrada que no
 * cambian el stock (CORREGIBLES), para completar lo que se olvidó cargar.
 *
 * Se crea sólo a través de App\Services\Inventario, que mueve el stock de la
 * variante en la misma transacción.
 */
#[Fillable(['grupo', 'variante_id', 'sede_id', 'sede_relacionada_id', 'pedido_id', 'compra_id', 'tipo', 'cantidad', 'stock_resultante', 'costo_unitario', 'motivo', 'referencia', 'observacion', 'user_id'])]
class MovimientoInventario extends Model
{
    public const ENTRADA = 'entrada';

    public const SALIDA = 'salida';

    public const AJUSTE = 'ajuste';

    /** Motivos de una salida manual (las ventas descontarán desde Pedidos). */
    public const MOTIVOS_SALIDA = [
        'merma' => 'Merma',
        'danado' => 'Dañado',
        'regalo' => 'Regalo / promoción',
        'devolucion_proveedor' => 'Devolución al proveedor',
        'uso_interno' => 'Uso interno',
        'otro' => 'Otro',
    ];

    public const MOTIVO_CONTEO = 'conteo';

    /** Los registra App\Services\Pedidos, no se eligen a mano. */
    public const MOTIVO_VENTA = 'venta';

    public const MOTIVO_DEVOLUCION_VENTA = 'devolucion_venta';

    /** Stock inicial cargado al crear el producto (o una variante nueva). */
    public const MOTIVO_ALTA_PRODUCTO = 'alta_producto';

    /** Los registra App\Services\Compras. */
    public const MOTIVO_COMPRA = 'compra';

    public const MOTIVO_ANULACION_COMPRA = 'anulacion_compra';

    /** Las dos patas de un traslado entre sedes (mismo grupo). */
    public const MOTIVO_TRASLADO_SALIDA = 'traslado_salida';

    public const MOTIVO_TRASLADO_ENTRADA = 'traslado_entrada';

    /** Etiqueta de cualquier motivo, para mostrar. */
    public static function etiquetaMotivo(?string $motivo): ?string
    {
        return match ($motivo) {
            null => null,
            self::MOTIVO_CONTEO => 'Conteo físico',
            self::MOTIVO_VENTA => 'Venta',
            self::MOTIVO_DEVOLUCION_VENTA => 'Pedido cancelado',
            self::MOTIVO_ALTA_PRODUCTO => 'Alta de producto',
            self::MOTIVO_COMPRA => 'Compra',
            self::MOTIVO_ANULACION_COMPRA => 'Compra anulada',
            self::MOTIVO_TRASLADO_SALIDA => 'Traslado enviado',
            self::MOTIVO_TRASLADO_ENTRADA => 'Traslado recibido',
            default => self::MOTIVOS_SALIDA[$motivo] ?? $motivo,
        };
    }

    /** Lo que Inventario::corregirEntrada() puede cambiar (no mueve stock). */
    public const CORREGIBLES = ['costo_unitario', 'referencia'];

    // Sólo created_at: las correcciones no cambian cuándo entró la mercadería.
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'stock_resultante' => 'float',
            'costo_unitario' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (MovimientoInventario $movimiento) {
            if (array_diff(array_keys($movimiento->getDirty()), self::CORREGIBLES)) {
                throw new LogicException('Los movimientos de inventario no se editan: registrá otro que lo corrija.');
            }
        });
        static::deleting(fn () => throw new LogicException('Los movimientos de inventario no se borran: registrá otro que lo corrija.'));
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    /**
     * Los lotes que movió, con la cantidad de cada uno en el pivot (con
     * el mismo signo que el movimiento).
     */
    public function lotes(): BelongsToMany
    {
        return $this->belongsToMany(Lote::class, 'movimiento_lotes')->withPivot('cantidad');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function sedeRelacionada(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_relacionada_id');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
