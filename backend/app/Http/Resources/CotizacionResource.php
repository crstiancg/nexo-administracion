<?php

namespace App\Http\Resources;

use App\Models\Cotizacion;
use App\Support\NumeroALetras;
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
            // normal | pc (PC armada por componentes).
            'tipo' => $this->tipo,
            'mostrar_precios' => $this->mostrar_precios,
            // PC armada: los ítems son de UN equipo y el total es por todos.
            'equipos' => $this->multiplicador(),
            // pendiente | vencida | convertida | rechazada
            'estado' => $this->estadoVisible(),
            'editable' => $this->editable(),
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente ? new ClienteResource($this->cliente) : null),
            'sede' => $this->whenLoaded('sede', fn () => $this->sede?->only(['id', 'nombre', 'direccion', 'telefono', 'horario'])),
            'fecha' => $this->created_at?->toIso8601String(),
            'valida_hasta' => $this->valida_hasta?->toDateString(),
            'forma_pago' => $this->forma_pago,
            'entrega' => $this->entrega,
            'garantia' => $this->garantia,
            'condiciones' => $this->condiciones,
            'observacion' => $this->observacion,
            'subtotal' => $this->subtotal,
            'descuento' => $this->descuento,
            'total' => $this->total,
            // IGV incluido en el total: base imponible + IGV = total.
            'op_gravada' => $this->op_gravada,
            'igv' => $this->igv,
            // "SON OCHOCIENTOS … CON 00/100 SOLES": va impreso en la proforma.
            'total_letras' => NumeroALetras::convertir((float) $this->total, 'SOLES'),
            'items_count' => $this->whenCounted('items'),
            // Con VarianteStockResource: el form edita los ítems igual que
            // los de un pedido (producto, presentación, stock de hoy).
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'variante_id' => $item->variante_id,
                'componente' => $item->componente,
                // Libre (fuera del catálogo): sin variante, con su descripción.
                'descripcion' => $item->descripcion,
                'unidad' => $item->unidad,
                'cantidad' => $item->cantidad,
                'precio_unitario' => $item->precio_unitario,
                'subtotal' => $item->subtotal,
                // [] o [foto]: el mismo formato que las fotos de un producto.
                'archivos' => ArchivoResource::collection($item->archivos)->resolve($request),
                'variante' => $item->variante ? (new VarianteStockResource($item->variante))->resolve($request) : null,
            ])),
            'pedido' => $this->whenLoaded('pedido', fn () => $this->pedido?->only(['id', 'codigo', 'estado'])),
            'orden_servicio' => $this->whenLoaded('ordenServicio', fn () => $this->ordenServicio?->only(['id', 'codigo', 'estado'])),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'name'])),
        ];
    }
}
