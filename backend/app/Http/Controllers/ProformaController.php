<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArchivoResource;
use App\Models\Cotizacion;
use App\Support\Empresa;
use App\Support\NumeroALetras;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La proforma pública: el cliente abre el enlace que le llegó por WhatsApp y
 * la ve (y la imprime o guarda en PDF) sin sesión, por el token. Sólo lo que
 * sale impreso: nada de notas internas, costos ni stock.
 */
class ProformaController extends Controller
{
    public function show(Request $request, string $token): JsonResponse
    {
        $cotizacion = Cotizacion::query()
            ->where('token', $token)
            ->with([
                'cliente:id,nombre,tipo_documento,numero_documento,telefono,direccion',
                'sede:id,nombre,direccion,telefono,horario',
                'usuario:id,name',
                'items' => fn ($q) => $q->orderBy('id'),
                'items.variante:id,producto_id,presentacion,sku,unidad_id,color_id',
                'items.variante.producto:id,nombre',
                'items.variante.unidad:id,abreviatura',
                'items.variante.color:id,nombre',
                'items.archivos',
            ])
            ->firstOrFail();

        $empresa = Empresa::datos();
        unset($empresa['whatsapp']);

        return response()->json([
            'empresa' => $empresa,
            'cotizacion' => [
                'codigo' => $cotizacion->codigo,
                'tipo' => $cotizacion->tipo,
                'mostrar_precios' => $cotizacion->mostrar_precios,
                'equipos' => $cotizacion->multiplicador(),
                // pendiente | vencida | convertida | rechazada
                'estado' => $cotizacion->estadoVisible(),
                'cliente' => $cotizacion->cliente?->only(['nombre', 'tipo_documento', 'numero_documento', 'telefono', 'direccion']),
                'sede' => $cotizacion->sede?->only(['nombre', 'direccion', 'telefono', 'horario']),
                'usuario' => $cotizacion->usuario?->only(['name']),
                'fecha' => $cotizacion->created_at?->toIso8601String(),
                'valida_hasta' => $cotizacion->valida_hasta?->toDateString(),
                'forma_pago' => $cotizacion->forma_pago,
                'entrega' => $cotizacion->entrega,
                'garantia' => $cotizacion->garantia,
                'condiciones' => $cotizacion->condiciones,
                'subtotal' => $cotizacion->subtotal,
                'descuento' => $cotizacion->descuento,
                'total' => $cotizacion->total,
                'op_gravada' => $cotizacion->op_gravada,
                'igv' => $cotizacion->igv,
                'total_letras' => NumeroALetras::convertir((float) $cotizacion->total, 'SOLES'),
                'items' => $cotizacion->items->map(fn ($item) => [
                    'id' => $item->id,
                    'componente' => $item->componente,
                    'descripcion' => $item->descripcion,
                    'unidad' => $item->unidad,
                    'cantidad' => $item->cantidad,
                    'precio_unitario' => $item->precio_unitario,
                    'subtotal' => $item->subtotal,
                    'archivos' => ArchivoResource::collection($item->archivos)->resolve($request),
                    // Sólo lo que describe el producto (sin stock ni costo).
                    'variante' => $item->variante ? [
                        'sku' => $item->variante->sku,
                        'presentacion' => $item->variante->presentacion,
                        'producto' => $item->variante->producto?->only(['nombre']),
                        'unidad' => $item->variante->unidad?->only(['abreviatura']),
                        'color' => $item->variante->color?->only(['nombre']),
                    ] : null,
                ]),
            ],
        ]);
    }
}
