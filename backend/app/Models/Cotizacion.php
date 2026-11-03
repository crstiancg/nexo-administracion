<?php

namespace App\Models;

use App\Support\Igv;
use App\Support\Fechas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * pendiente → convertida (en un pedido) | rechazada. Una pendiente con
 * `valida_hasta` pasada se muestra como vencida. Los cambios de estado pasan
 * por App\Services\Cotizaciones.
 */
#[Fillable(['cliente_id', 'valida_hasta', 'condiciones', 'observacion', 'descuento'])]
class Cotizacion extends Model
{
    public const PENDIENTE = 'pendiente';

    public const CONVERTIDA = 'convertida';

    public const RECHAZADA = 'rechazada';

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
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
            'op_gravada' => 'decimal:2',
            'igv' => 'decimal:2',
        ];
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
