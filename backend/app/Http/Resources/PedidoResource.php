<?php

namespace App\Http\Resources;

use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sirve para el listado (con cliente y cantidad de ítems) y para el detalle
 * (con ítems y ganancia): cada bloque aparece sólo si se cargó.
 *
 * @mixin Pedido
 */
class PedidoResource extends JsonResource
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
            'editable' => $this->editable(),
            'sede' => $this->whenLoaded('sede', fn () => $this->sede?->only(['id', 'nombre', 'direccion', 'telefono'])),
            'canal' => $this->canal,
            'canal_label' => Pedido::CANALES[$this->canal] ?? $this->canal,
            'cliente_id' => $this->cliente_id,
            // null = "Cliente varios".
            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente ? new ClienteResource($this->cliente) : null),
            'subtotal' => $this->subtotal,
            'descuento' => $this->descuento,
            'total' => $this->total,
            // IGV incluido en el total: base imponible + IGV = total.
            'op_gravada' => $this->op_gravada,
            'igv' => $this->igv,
            // Cobrado neto (devoluciones restan) y lo que falta cobrar.
            'pagado' => $this->when(($pagado = $this->pagadoCargado()) !== null, fn () => number_format($pagado, 2, '.', '')),
            'saldo' => $this->when($pagado !== null, fn () => number_format((float) $this->total - $pagado, 2, '.', '')),
            'pagos' => PagoResource::collection($this->whenLoaded('pagos')),
            // El comprobante electrónico que vale (o el último, si todos se
            // rechazaron): null = sólo ticket.
            'comprobante' => $this->whenLoaded('comprobantes', function () {
                $comprobante = $this->resource->comprobanteVigente() ?? $this->comprobantes->first();

                return $comprobante ? (new ComprobanteResource($comprobante))->resolve(request()) : null;
            }),
            'observacion' => $this->observacion,
            'items_count' => $this->whenCounted('items'),
            'items' => PedidoItemResource::collection($this->whenLoaded('items')),
            'ganancia' => $this->when($this->relationLoaded('items'), fn () => $this->ganancia()),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'name'])),
            'fecha' => $this->created_at?->toIso8601String(),
            'confirmado_at' => $this->confirmado_at?->toIso8601String(),
            'entregado_at' => $this->entregado_at?->toIso8601String(),
            'cancelado_at' => $this->cancelado_at?->toIso8601String(),
            'anulado_at' => $this->anulado_at?->toIso8601String(),
            'anulado_por' => $this->whenLoaded('anuladoPor', fn () => $this->anuladoPor?->only(['id', 'name'])),
            'motivo_anulacion' => $this->motivo_anulacion,
        ];
    }

    /**
     * Lo pagado sin consultas extra: de los pagos cargados (detalle) o de la
     * suma que trajo el listado (withSum). null si no se pidió ninguno.
     */
    private function pagadoCargado(): ?float
    {
        if ($this->relationLoaded('pagos')) {
            return round((float) $this->pagos->sum('monto'), 2);
        }

        return array_key_exists('pagos_sum_monto', $this->getAttributes())
            ? round((float) $this->pagos_sum_monto, 2)
            : null;
    }

    /**
     * Total cobrado menos lo que costó la mercadería, con el costo congelado
     * al confirmar. null si todavía no se confirmó o falta algún costo: una
     * ganancia calculada con costos faltantes sería una mentira.
     */
    private function ganancia(): ?string
    {
        if (! in_array($this->estado, [Pedido::CONFIRMADO, Pedido::ENTREGADO], true)) {
            return null;
        }
        if ($this->items->contains(fn ($item) => $item->costo_unitario === null)) {
            return null;
        }

        $costo = $this->items->sum(fn ($item) => (float) $item->cantidad * (float) $item->costo_unitario);

        return number_format((float) $this->total - $costo, 2, '.', '');
    }
}
