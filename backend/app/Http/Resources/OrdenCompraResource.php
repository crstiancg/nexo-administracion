<?php

namespace App\Http\Resources;

use App\Models\OrdenCompra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrdenCompra
 */
class OrdenCompraResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            // pendiente | enviada | recibida | anulada
            'estado' => $this->estado,
            'editable' => $this->editable(),
            'abierta' => $this->abierta(),
            'proveedor_id' => $this->proveedor_id,
            'proveedor' => $this->whenLoaded('proveedor', fn () => $this->proveedor?->only(['id', 'ruc', 'razon_social', 'contacto', 'telefono', 'email', 'direccion'])),
            'sede' => $this->whenLoaded('sede', fn () => $this->sede?->only(['id', 'nombre', 'direccion', 'telefono'])),
            'fecha' => $this->created_at?->toIso8601String(),
            'fecha_entrega' => $this->fecha_entrega?->toDateString(),
            'condiciones' => $this->condiciones,
            'observacion' => $this->observacion,
            // Estimado: suma de las líneas con costo.
            'total' => $this->total,
            'op_gravada' => $this->op_gravada,
            'igv' => $this->igv,
            'items_count' => $this->whenCounted('items'),
            // Con VarianteStockResource: el form edita los ítems con el stock
            // y el costo promedio de hoy, y la recepción sabe si piden lote.
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'variante_id' => $item->variante_id,
                'cantidad' => $item->cantidad,
                'costo_unitario' => $item->costo_unitario,
                'subtotal' => $item->subtotal,
                'variante' => (new VarianteStockResource($item->variante))->resolve($request),
            ])),
            'compra' => $this->whenLoaded('compra', fn () => $this->compra?->only(['id', 'codigo', 'estado'])),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'name'])),
            'enviada_at' => $this->enviada_at?->toIso8601String(),
            'anulada_at' => $this->anulada_at?->toIso8601String(),
            'motivo_anulacion' => $this->motivo_anulacion,
        ];
    }
}
