<?php

namespace App\Http\Resources;

use App\Models\Caja;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Caja
 */
class CajaResource extends JsonResource
{
    /**
     * Totales calculados (App\Services\Cajas::resumen) cuando se pidieron.
     *
     * @var array<string, mixed>|null
     */
    private ?array $resumen = null;

    /**
     * @param  array<string, mixed>  $resumen
     */
    public function conResumen(array $resumen): static
    {
        $this->resumen = $resumen;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->estado,
            'sede' => $this->whenLoaded('sede', fn () => $this->sede?->only(['id', 'nombre'])),
            'monto_apertura' => $this->monto_apertura,
            'abierta_at' => $this->abierta_at?->toIso8601String(),
            // Abierta en un día anterior: no cobra hasta cerrarla.
            'vencida' => $this->resource->esDeOtroDia(),
            // Desde qué hora el punto de venta avisa que hay que cerrarla.
            'hora_aviso_cierre' => (int) config('app.hora_aviso_cierre'),
            'abierta_por' => $this->whenLoaded('abiertaPor', fn () => $this->abiertaPor?->only(['id', 'name'])),
            'cerrada_at' => $this->cerrada_at?->toIso8601String(),
            'cerrada_por' => $this->whenLoaded('cerradaPor', fn () => $this->cerradaPor?->only(['id', 'name'])),
            // Arqueo (sólo al cerrar): diferencia negativa = faltante.
            'monto_esperado' => $this->monto_esperado,
            'monto_contado' => $this->monto_contado,
            'diferencia' => $this->diferencia,
            'observacion_cierre' => $this->observacion_cierre,
            'resumen' => $this->when($this->resumen !== null, fn () => $this->resumen),
            'pagos' => PagoResource::collection($this->whenLoaded('pagos')),
            'movimientos' => $this->whenLoaded('movimientos', fn () => $this->movimientos->map(fn ($m) => [
                'id' => $m->id,
                'tipo' => $m->tipo,
                'monto' => $m->monto,
                'concepto' => $m->concepto,
                'fecha' => $m->created_at?->toIso8601String(),
                'usuario' => $m->usuario?->only(['id', 'name']),
            ])),
        ];
    }
}
