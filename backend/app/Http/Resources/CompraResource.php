<?php

namespace App\Http\Resources;

use App\Models\Compra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Listado (con proveedor y cantidad de ítems) y detalle (con ítems): cada
 * bloque aparece sólo si se cargó.
 *
 * @mixin Compra
 */
class CompraResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'estado' => $this->estado,
            'tipo_documento' => $this->tipo_documento,
            'tipo_documento_label' => Compra::TIPOS_DOCUMENTO[$this->tipo_documento] ?? $this->tipo_documento,
            'numero_documento' => $this->numero_documento,
            'fecha' => $this->fecha?->toDateString(),
            'total' => $this->total,
            // IGV incluido en el total: base imponible + IGV = total.
            'op_gravada' => $this->op_gravada,
            'igv' => $this->igv,
            'observacion' => $this->observacion,
            'proveedor' => $this->whenLoaded('proveedor', fn () => $this->proveedor?->only(['id', 'ruc', 'razon_social', 'telefono', 'direccion'])),
            'sede' => $this->whenLoaded('sede', fn () => $this->sede?->only(['id', 'nombre'])),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'name'])),
            'items_count' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'variante_id' => $item->variante_id,
                'cantidad' => $item->cantidad,
                'costo_unitario' => $item->costo_unitario,
                'subtotal' => $item->subtotal,
                'lote' => $item->lote,
                'vence_at' => $item->vence_at?->toDateString(),
                'variante' => [
                    'sku' => $item->variante->sku,
                    'presentacion' => $item->variante->presentacion,
                    'producto' => $item->variante->producto?->only(['id', 'nombre']),
                    'unidad' => $item->variante->unidad?->abreviatura,
                    'color' => $item->variante->color?->only(['nombre', 'hexadecimal']),
                ],
            ])),
            'fecha_registro' => $this->created_at?->toIso8601String(),
            'anulada_at' => $this->anulada_at?->toIso8601String(),
            'anulada_por' => $this->whenLoaded('anuladaPor', fn () => $this->anuladaPor?->only(['id', 'name'])),
            'motivo_anulacion' => $this->motivo_anulacion,
        ];
    }
}
