<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea del pedido con precio (y, al confirmar, costo) congelados: lo
 * que se cobró y lo que costó en ESE momento. Sin `variante_id` es un
 * servicio (la mano de obra de una orden): lleva `descripcion` y no mueve stock.
 */
#[Fillable(['variante_id', 'descripcion', 'cantidad', 'precio_unitario', 'por_mayor', 'subtotal'])]
class PedidoItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'precio_unitario' => 'decimal:2',
            'por_mayor' => 'boolean',
            'costo_unitario' => 'decimal:4',
            'subtotal' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function esServicio(): bool
    {
        return $this->variante_id === null;
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }
}
