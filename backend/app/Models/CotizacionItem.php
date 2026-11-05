<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CotizacionItem extends Model
{
    public $timestamps = false;

    protected $table = 'cotizacion_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    /** Lo que la tienda no tiene: sin variante, se describe a mano. */
    public function libre(): bool
    {
        return $this->variante_id === null;
    }

    /** La foto opcional que sale en la proforma (a lo sumo una). */
    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->orderBy('orden');
    }
}
