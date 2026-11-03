<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una presentación en una sede: cuánto hay y cómo la vende esa sede (si la
 * vende, a qué precio y con qué mínimo). Sin fillable a propósito: la
 * cantidad la mueve sólo App\Services\Inventario y la configuración el
 * formulario de productos (ProductoController).
 */
class Stock extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'activo' => 'boolean',
            'precio' => 'decimal:2',
            'stock_minimo' => 'float',
        ];
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
