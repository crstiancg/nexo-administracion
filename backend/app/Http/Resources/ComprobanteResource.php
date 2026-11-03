<?php

namespace App\Http\Resources;

use App\Models\Comprobante;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Comprobante
 */
class ComprobanteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'pedido' => $this->whenLoaded('pedido', fn () => $this->pedido?->only(['id', 'codigo'])),
            'tipo' => $this->tipo,
            'tipo_label' => Comprobante::TIPOS[$this->tipo] ?? $this->tipo,
            // El emisor tal cual se mandó a SUNAT (config/greenter.php): para
            // la representación impresa.
            'emisor' => [
                'ruc' => config('greenter.company.ruc'),
                'razon_social' => config('greenter.company.razonSocial'),
                'direccion' => config('greenter.company.address.direccion'),
            ],
            'numero' => $this->numero(),
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'fecha_emision' => $this->fecha_emision?->toIso8601String(),
            'cliente' => [
                'tipo_doc' => $this->cliente_tipo_doc,
                'num_doc' => $this->cliente_num_doc,
                'nombre' => $this->cliente_nombre,
            ],
            'op_gravada' => $this->op_gravada,
            'igv' => $this->igv,
            'descuento' => $this->descuento,
            'total' => $this->total,
            'total_letras' => $this->total_letras,
            // pendiente | aceptado | rechazado | error (no llegó: se reenvía)
            'estado' => $this->estado,
            'sunat_codigo' => $this->sunat_codigo,
            'sunat_mensaje' => $this->sunat_mensaje,
            'hash' => $this->hash,
            // QR de la representación impresa, como SVG.
            'qr_svg' => $this->qrSvg(),
            'tiene_xml' => (bool) $this->xml_path,
            'tiene_cdr' => (bool) $this->cdr_path,
            'sede' => $this->whenLoaded('sede', fn () => $this->sede?->only(['id', 'nombre'])),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'name'])),
        ];
    }
}
