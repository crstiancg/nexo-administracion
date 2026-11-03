<?php

namespace App\Http\Resources;

use App\Models\Cotizacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cotizacion
 */
class CotizacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            // pendiente | vencida | convertida | rechazada
            'estado' => $this->estadoVisible(),
            'editable' => $this->editable(),
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente ? new ClienteResource($this->cliente) : null),
            'sede' => $this->whenLoaded('sede', fn () => $this->sede?->only(['id', 'nombre', 'direccion', 'telefono'])),
            'fecha' => $this->created_at?->toIso8601String(),
            'valida_hasta' => $this->valida_hasta?->toDateString(),
            'condiciones' => $this->condiciones,
            'observacion' => $this->observacion,
            'subtotal' => $this->subtotal,
            'descuento' => $this->descuento,
            'total' => $this->total,
            // IGV incluido en el total: base imponible + IGV = total.
            'op_gravada' => $this->op_gravada,
            'igv' => $this->igv,
            'items_count' => $this->whenCounted('items'),
            // Con VarianteStockResource: el form edita los ítems igual que
            // los de un pedido (producto, presentación, stock de hoy).
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'variante_id' => $item->variante_id,
                'cantidad' => $item->cantidad,
                'precio_unitario' => $item->precio_unitario,
                'subtotal' => $item->subtotal,
                'variante' => (new VarianteStockResource($item->variante))->resolve($request),
            ])),
            'pedido' => $this->whenLoaded('pedido', fn () => $this->pedido?->only(['id', 'codigo', 'estado'])),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'name'])),
        ];
    }
}
