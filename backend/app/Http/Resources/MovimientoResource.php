<?php

namespace App\Http\Resources;

use App\Models\MovimientoInventario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MovimientoInventario
 */
class MovimientoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grupo' => $this->grupo,
            'tipo' => $this->tipo,
            'sede' => $this->whenLoaded('sede', fn () => $this->sede?->only(['id', 'nombre'])),
            // En un traslado, la otra sede.
            'sede_relacionada' => $this->whenLoaded('sedeRelacionada', fn () => $this->sedeRelacionada?->only(['id', 'nombre'])),
            // Con signo: +10 entra, -2 sale.
            'cantidad' => $this->cantidad,
            'stock_resultante' => $this->stock_resultante,
            'costo_unitario' => $this->costo_unitario,
            'lotes' => $this->whenLoaded('lotes', fn () => $this->lotes->map(fn ($lote) => [
                'id' => $lote->id,
                'codigo' => $lote->codigo,
                'vence_at' => $lote->vence_at?->toDateString(),
                'cantidad' => (float) $lote->pivot->cantidad,
            ])),
            'motivo' => $this->motivo,
            'motivo_label' => MovimientoInventario::etiquetaMotivo($this->motivo),
            'pedido_id' => $this->pedido_id,
            // Una entrada de compra se corrige desde la compra, no suelta.
            'compra_id' => $this->compra_id,
            'referencia' => $this->referencia,
            'observacion' => $this->observacion,
            'fecha' => $this->created_at?->toIso8601String(),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'name'])),
            'variante' => $this->whenLoaded('variante', fn () => new VarianteStockResource($this->variante)),
        ];
    }
}
