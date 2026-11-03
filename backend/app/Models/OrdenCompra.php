<?php

namespace App\Models;

use App\Support\Igv;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * pendiente → enviada (al proveedor) → recibida (con una compra) | anulada.
 * No mueve stock: la mercadería entra con la compra que la recibe. Los
 * cambios de estado pasan por App\Services\OrdenesCompra.
 */
#[Fillable(['proveedor_id', 'fecha_entrega', 'condiciones', 'observacion'])]
class OrdenCompra extends Model
{
    public const PENDIENTE = 'pendiente';

    public const ENVIADA = 'enviada';

    public const RECIBIDA = 'recibida';

    public const ANULADA = 'anulada';

    /** Explícita: por convención Laravel pluraliza en inglés. */
    protected $table = 'ordenes_compra';

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
            'fecha_entrega' => 'date',
            'total' => 'decimal:2',
            'op_gravada' => 'decimal:2',
            'igv' => 'decimal:2',
            'enviada_at' => 'datetime',
            'anulada_at' => 'datetime',
        ];
    }

    /**
     * El estado para los mensajes: en pantalla es "pedido de compra"
     * (masculino), aunque en la base quede como `enviada`, `recibida`…
     */
    public function estadoTexto(): string
    {
        return match ($this->estado) {
            self::ENVIADA => 'enviado',
            self::RECIBIDA => 'recibido',
            self::ANULADA => 'anulado',
            default => $this->estado,
        };
    }

    /** Se edita mientras no se mandó al proveedor. */
    public function editable(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    /** Todavía se espera la mercadería: se puede recibir o anular. */
    public function abierta(): bool
    {
        return in_array($this->estado, [self::PENDIENTE, self::ENVIADA], true);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrdenCompraItem::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
