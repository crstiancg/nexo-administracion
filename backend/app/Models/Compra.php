<?php

namespace App\Models;

use App\Support\Igv;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Se registra y se anula sólo a través de App\Services\Compras, que mueve el
 * inventario en la misma transacción (sin fillable a propósito).
 */
class Compra extends Model
{
    public const REGISTRADA = 'registrada';

    public const ANULADA = 'anulada';

    public const TIPOS_DOCUMENTO = [
        'factura' => 'Factura',
        'boleta' => 'Boleta',
        'guia' => 'Guía de remisión',
        'otro' => 'Otro',
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
            'fecha' => 'date',
            'total' => 'decimal:2',
            'op_gravada' => 'decimal:2',
            'igv' => 'decimal:2',
            'anulada_at' => 'datetime',
        ];
    }

    /** "Factura F001-2345", para la referencia de los movimientos. */
    public function documento(): string
    {
        return trim((self::TIPOS_DOCUMENTO[$this->tipo_documento] ?? $this->tipo_documento).' '.$this->numero_documento);
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
        return $this->hasMany(CompraItem::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }
}
