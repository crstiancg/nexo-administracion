<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PedidoItem
 */
class PedidoItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'variante_id' => $this->variante_id,
            'cantidad' => $this->cantidad,
            'precio_unitario' => $this->precio_unitario,
            'por_mayor' => (bool) $this->por_mayor,
            'costo_unitario' => $this->costo_unitario,
            'subtotal' => $this->subtotal,
            'variante' => $this->whenLoaded('variante', fn () => new VarianteStockResource($this->variante)),
        ];
    }
}
