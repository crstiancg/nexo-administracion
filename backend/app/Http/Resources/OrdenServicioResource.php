<?php

namespace App\Http\Resources;

use App\Models\EstadoOrden;
use App\Models\OrdenServicio;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Sirve para el listado y para el detalle: cada bloque aparece sólo si se
 * cargó. La clave del equipo sale sólo en el detalle y a quien puede editar.
 *
 * @mixin OrdenServicio
 */
class OrdenServicioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pedido = $this->relationLoaded('pedido') ? $this->pedido : null;
        $pagado = $pedido ? $this->pagado($pedido) : null;

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'estado' => $this->estado,
            'estado_label' => EstadoOrden::nombreDe($this->estado),
            'editable' => $this->editable(),
            'token' => $this->token,
            'sede' => $this->whenLoaded('sede', fn () => $this->sede?->only(['id', 'nombre', 'direccion', 'telefono'])),
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->whenLoaded('cliente', fn () => new ClienteResource($this->cliente)),
            'tecnico_id' => $this->tecnico_id,
            'tecnico' => $this->whenLoaded('tecnico', fn () => $this->tecnico?->only(['id', 'name'])),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'name'])),
            'tipo_equipo_id' => $this->tipo_equipo_id,
            'tipo_equipo' => $this->whenLoaded('tipoEquipo', fn () => $this->tipoEquipo?->only(['id', 'nombre', 'checklist'])),
            'marca_id' => $this->marca_id,
            'marca' => $this->whenLoaded('marca', fn () => $this->marca?->only(['id', 'nombre'])),
            'modelo' => $this->modelo,
            'equipo' => $this->when($this->relationLoaded('tipoEquipo'), fn () => $this->equipo()),
            'serie' => $this->serie,
            'accesorios' => $this->accesorios,
            'seguridad_tipo' => $this->seguridad_tipo,
            'seguridad_label' => OrdenServicio::SEGURIDAD[$this->seguridad_tipo] ?? $this->seguridad_tipo,
            'seguridad_valor' => $this->when(
                $this->relationLoaded('movimientos') && Permisos::puede($request->user(), 'ordenes-servicio.update'),
                fn () => $this->seguridad_valor,
            ),
            'checklist' => $this->checklist ?? (object) [],
            'fotos' => ArchivoResource::collection($this->whenLoaded('archivos')),
            'firma_recepcion_url' => $this->firma_recepcion ? Storage::disk('public')->url($this->firma_recepcion) : null,
            'firma_entrega_url' => $this->firma_entrega ? Storage::disk('public')->url($this->firma_entrega) : null,
            'falla' => $this->falla,
            'diagnostico' => $this->diagnostico,
            'observacion' => $this->observacion,
            'es_garantia' => $this->es_garantia,
            'garantia_dias' => $this->garantia_dias,
            'garantia_hasta' => $this->garantiaHasta()?->toDateString(),
            'mano_obra' => $this->mano_obra,
            // El cobro: total del pedido, lo abonado y lo que falta.
            'pedido_id' => $this->pedido_id,
            'pedido' => $this->when($pedido !== null, fn () => [
                'id' => $pedido->id,
                'codigo' => $pedido->codigo,
                'estado' => $pedido->estado,
            ]),
            'total' => $pedido?->total,
            // El saldo se financió en cuotas (null = sin crédito).
            'credito_id' => $this->when($pedido !== null, fn () => $pedido->credito()->value('id')),
            'pagado' => $pagado === null ? null : number_format($pagado, 2, '.', ''),
            'saldo' => $pagado === null ? null : number_format((float) $pedido->total - $pagado, 2, '.', ''),
            'repuestos' => $this->when(
                $pedido?->relationLoaded('items'),
                fn () => PedidoItemResource::collection($pedido->items->whereNotNull('variante_id')->values()),
            ),
            'pagos' => $this->when($pedido?->relationLoaded('pagos'), fn () => PagoResource::collection($pedido->pagos)),
            'comprobante' => $this->when($pedido?->relationLoaded('comprobantes'), function () use ($pedido) {
                $comprobante = $pedido->comprobanteVigente() ?? $pedido->comprobantes->first();

                return $comprobante ? (new ComprobanteResource($comprobante))->resolve(request()) : null;
            }),
            'movimientos' => $this->whenLoaded('movimientos', fn () => $this->movimientos->map(fn ($m) => [
                'id' => $m->id,
                'estado' => $m->estado,
                'estado_label' => EstadoOrden::nombreDe($m->estado),
                'nota' => $m->nota,
                'usuario' => $m->usuario?->only(['id', 'name']),
                'fecha' => $m->created_at?->toIso8601String(),
            ])),
            'recibido_at' => $this->recibido_at?->toIso8601String(),
            'entregado_at' => $this->entregado_at?->toIso8601String(),
        ];
    }

    /** Lo abonado sin consultas extra: pagos cargados o la suma del listado. */
    private function pagado($pedido): ?float
    {
        if ($pedido->relationLoaded('pagos')) {
            return round((float) $pedido->pagos->sum('monto'), 2);
        }

        return array_key_exists('pagos_sum_monto', $pedido->getAttributes())
            ? round((float) $pedido->pagos_sum_monto, 2)
            : null;
    }
}
