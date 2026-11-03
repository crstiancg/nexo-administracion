<?php

namespace App\Models;

use App\Support\Igv;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * pendiente → confirmado → entregado
 *        ↘           ↘
 *         cancelado   cancelado (devuelve el stock)
 *
 * Los cambios de estado pasan sólo por App\Services\Pedidos: confirmar y
 * cancelar mueven stock a través de App\Services\Inventario.
 */
#[Fillable(['cliente_id', 'canal', 'descuento', 'observacion', 'user_id'])]
class Pedido extends Model
{
    public const PENDIENTE = 'pendiente';

    public const CONFIRMADO = 'confirmado';

    public const ENTREGADO = 'entregado';

    public const CANCELADO = 'cancelado';

    /** Una venta (confirmada o entregada) que se deshizo: volvió stock y plata. */
    public const ANULADO = 'anulado';

    public const CANALES = [
        'mostrador' => 'Mostrador',
        'whatsapp' => 'WhatsApp',
        'redes' => 'Redes sociales',
        'web' => 'Web',
        // Nace de convertir una cotización.
        'cotizacion' => 'Cotización',
    ];

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
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
            'op_gravada' => 'decimal:2',
            'igv' => 'decimal:2',
            'confirmado_at' => 'datetime',
            'entregado_at' => 'datetime',
            'cancelado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    /** El comprobante que vale (aceptado, o en camino); null = sólo ticket. */
    public function comprobanteVigente(): ?Comprobante
    {
        return $this->comprobantes->first(fn (Comprobante $c) => $c->vigente());
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * Lo cobrado neto (las devoluciones son pagos negativos).
     */
    public function pagado(): float
    {
        return round((float) $this->pagos()->sum('monto'), 2);
    }

    public function saldo(): float
    {
        return round((float) $this->total - $this->pagado(), 2);
    }

    public function editable(): bool
    {
        return $this->estado === self::PENDIENTE;
    }
}
