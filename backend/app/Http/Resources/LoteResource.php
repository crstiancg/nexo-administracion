<?php

namespace App\Http\Resources;

use App\Models\Lote;
use App\Support\Fechas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lote
 */
class LoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'vence_at' => $this->vence_at?->toDateString(),
            // Negativo = vencido hace N días.
            'dias' => $this->vence_at ? (int) Fechas::hoy()->diffInDays($this->vence_at, false) : null,
            'estado' => $this->estado(),
            'cantidad' => $this->cantidad,
            'sede' => $this->whenLoaded('sede', fn () => $this->sede?->only(['id', 'nombre'])),
            'variante' => $this->whenLoaded('variante', fn () => [
                'id' => $this->variante->id,
                'sku' => $this->variante->sku,
                'presentacion' => $this->variante->presentacion,
                'producto' => $this->variante->producto?->only(['id', 'nombre']),
                'unidad' => $this->variante->unidad?->abreviatura,
                'color' => $this->variante->color?->only(['nombre', 'hexadecimal']),
            ]),
        ];
    }
}
