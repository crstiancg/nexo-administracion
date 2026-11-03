<?php

namespace App\Http\Resources;

use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pago
 */
class PagoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'caja_id' => $this->caja_id,
            'metodo' => $this->metodo,
            'metodo_label' => Pago::METODOS[$this->metodo] ?? $this->metodo,
            // Negativo = devolución.
            'monto' => $this->monto,
            'es_devolucion' => $this->esDevolucion(),
            'recibido' => $this->recibido,
            'vuelto' => $this->vuelto,
            'referencia' => $this->referencia,
            'motivo' => $this->motivo,
            'fecha' => $this->created_at?->toIso8601String(),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'name'])),
            'pedido' => $this->whenLoaded('pedido', fn () => $this->pedido->only(['id', 'codigo'])),
        ];
    }
}
