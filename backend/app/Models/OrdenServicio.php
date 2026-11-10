<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Un equipo que entra al taller.
 *
 * Los estados se configuran en Catálogos > Estados de orden (App\Models\EstadoOrden):
 * la orden guarda la clave y el tipo del estado decide qué se puede hacer
 * (desde dónde se entrega, qué cuenta como "en el taller"…). Entregado es final.
 *
 * El cobro vive en `pedido` (repuestos + mano de obra): los abonos son pagos
 * del pedido y la boleta o factura se emite sobre él. Los cambios de estado
 * pasan sólo por App\Services\OrdenesServicio.
 */
#[Fillable([
    'cliente_id', 'tecnico_id', 'tipo_equipo_id', 'marca_id', 'modelo', 'serie', 'accesorios',
    'seguridad_tipo', 'seguridad_valor', 'checklist', 'falla', 'diagnostico', 'observacion',
    'es_garantia', 'garantia_dias', 'mano_obra',
])]
class OrdenServicio extends Model
{
    // Claves de los estados de fábrica (migración create_estados_orden). Los
    // usan los seeders y los tests; la lógica pregunta por el tipo.
    public const RECIBIDO = 'recibido';

    public const DIAGNOSTICO = 'diagnostico';

    public const ESPERANDO_REPUESTO = 'esperando_repuesto';

    public const EN_REPARACION = 'en_reparacion';

    public const REPARADO = 'reparado';

    public const SIN_REPARACION = 'sin_reparacion';

    public const ENTREGADO = 'entregado';

    public const ABANDONADO = 'abandonado';

    /** Avisos del taller: listo y sin recoger / demasiado tiempo adentro. */
    public const DIAS_SIN_RECOGER = 3;

    public const DIAS_ATRASO = 7;

    public const SEGURIDAD = [
        'ninguno' => 'Sin bloqueo',
        'pin' => 'PIN',
        'contrasena' => 'Contraseña',
        'patron' => 'Patrón',
    ];

    /**
     * Explícita: por convención Laravel pluraliza en inglés ("orden_servicios").
     */
    protected $table = 'ordenes_servicio';

    protected function casts(): array
    {
        return [
            // La clave del equipo del cliente no queda en texto plano.
            'seguridad_valor' => 'encrypted',
            'checklist' => 'array',
            'es_garantia' => 'boolean',
            'garantia_dias' => 'integer',
            'mano_obra' => 'decimal:2',
            'recibido_at' => 'datetime',
            'entregado_at' => 'datetime',
        ];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(TipoEquipo::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /** Fotos del equipo (al recibirlo, durante la reparación, al entregarlo). */
    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->orderBy('orden');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(OrdenServicioMovimiento::class);
    }

    /** "Laptop Lenovo IdeaPad 3": cómo se nombra el equipo en listas y mensajes. */
    public function equipo(): string
    {
        return trim(implode(' ', array_filter([
            $this->tipoEquipo?->nombre, $this->marca?->nombre, $this->modelo,
        ])));
    }

    public function editable(): bool
    {
        return $this->estado !== EstadoOrden::entregado();
    }

    /** Hasta cuándo cubre la garantía del trabajo (desde la entrega). */
    public function garantiaHasta(): ?Carbon
    {
        return $this->entregado_at && $this->garantia_dias > 0
            ? $this->entregado_at->copy()->addDays($this->garantia_dias)
            : null;
    }

    /** La página pública que abre el QR (seguimiento sin sesión). */
    public function urlSeguimiento(): string
    {
        return rtrim(config('empresa.frontend_url'), '/').'/seguimiento/'.$this->token;
    }
}
