<?php

namespace App\Http\Controllers;

use App\Support\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Configuración del sistema: datos de la empresa, textos de los documentos,
 * logo y mensajes de WhatsApp por estado de la orden.
 */
class ConfiguracionController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(Empresa::datos());
    }

    public function update(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'empresa' => ['required', 'array'],
            'empresa.nombre' => ['required', 'string', 'max:120'],
            'empresa.ruc' => ['nullable', 'digits:11'],
            'empresa.direccion' => ['nullable', 'string', 'max:200'],
            'empresa.telefono' => ['nullable', 'string', 'max:80'],
            'empresa.lema' => ['nullable', 'string', 'max:300'],
            'empresa.garantia' => ['nullable', 'string', 'max:500'],
            'empresa.pie_ticket' => ['nullable', 'string', 'max:200'],
            'empresa.terminos_orden' => ['nullable', 'string', 'max:1000'],
            'whatsapp' => ['nullable', 'array'],
            'whatsapp.*' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'empresa.nombre' => 'razón social',
            'empresa.ruc' => 'RUC',
            'empresa.direccion' => 'dirección',
            'empresa.telefono' => 'teléfono',
            'empresa.lema' => 'lema',
            'empresa.garantia' => 'nota de garantía',
            'empresa.pie_ticket' => 'pie del ticket',
            'empresa.terminos_orden' => 'términos de la orden',
        ]);

        // Sólo los estados que existen (una clave inventada no se guarda).
        $whatsapp = array_intersect_key((array) ($datos['whatsapp'] ?? []), array_flip(Empresa::ESTADOS_WHATSAPP));

        Empresa::guardar('empresa', array_intersect_key($datos['empresa'], array_flip(Empresa::CAMPOS)));
        Empresa::guardar('whatsapp', array_map(fn ($t) => is_string($t) ? trim($t) : null, $whatsapp));

        return response()->json(Empresa::datos());
    }

    public function logo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ], [], ['logo' => 'logo']);

        $anterior = Empresa::logoRuta();
        $ruta = $request->file('logo')->store('empresa', 'public');
        Empresa::guardar('logo', $ruta);
        if ($anterior) {
            Storage::disk('public')->delete($anterior);
        }

        return response()->json(Empresa::datos());
    }

    public function quitarLogo(): JsonResponse
    {
        if ($anterior = Empresa::logoRuta()) {
            Storage::disk('public')->delete($anterior);
        }
        Empresa::guardar('logo', null);

        return response()->json(Empresa::datos());
    }
}
