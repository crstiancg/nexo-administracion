<?php

namespace App\Http\Controllers;

use App\Models\EstadoOrden;
use App\Support\Empresa;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Configuración del sistema: datos de la empresa, textos de los documentos,
 * logo, mensajes de WhatsApp por estado de la orden y la tabla de precios
 * de referencia (% sobre el costo para sugerir el precio de venta).
 */
class ConfiguracionController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->respuesta());
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
            'caja' => ['nullable', 'array'],
            'caja.cierre_desde' => ['required_with:caja', 'date_format:H:i'],
            'caja.cierre_hasta' => ['required_with:caja', 'date_format:H:i', 'after:caja.cierre_desde'],
            'margenes' => ['nullable', 'array', 'max:20', $this->tramosEnOrden()],
            'margenes.*.hasta' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'margenes.*.porcentaje' => ['required', 'numeric', 'min:0', 'max:1000', 'decimal:0,2'],
        ], [
            'caja.cierre_hasta.after' => 'La hora límite tiene que ser después de la hora de aviso.',
        ], [
            'empresa.nombre' => 'razón social',
            'empresa.ruc' => 'RUC',
            'empresa.direccion' => 'dirección',
            'empresa.telefono' => 'teléfono',
            'empresa.lema' => 'lema',
            'empresa.garantia' => 'nota de garantía',
            'empresa.pie_ticket' => 'pie del ticket',
            'empresa.terminos_orden' => 'términos de la orden',
            'caja.cierre_desde' => 'aviso de cierre',
            'caja.cierre_hasta' => 'hora límite de cierre',
            'margenes.*.hasta' => 'costo hasta',
            'margenes.*.porcentaje' => 'porcentaje',
        ]);

        // Sólo los estados que existen (una clave inventada no se guarda).
        $whatsapp = array_intersect_key((array) ($datos['whatsapp'] ?? []), array_flip(Empresa::clavesWhatsapp()));

        Empresa::guardar('empresa', array_intersect_key($datos['empresa'], array_flip(Empresa::CAMPOS)));
        Empresa::guardar('whatsapp', array_map(fn ($t) => is_string($t) ? trim($t) : null, $whatsapp));
        if (isset($datos['caja'])) {
            Empresa::guardar('caja', array_intersect_key($datos['caja'], array_flip(['cierre_desde', 'cierre_hasta'])));
        }
        if (array_key_exists('margenes', $datos)) {
            Empresa::guardar('margenes', array_map(fn ($t) => [
                'hasta' => isset($t['hasta']) && $t['hasta'] !== '' ? round((float) $t['hasta'], 2) : null,
                'porcentaje' => round((float) $t['porcentaje'], 2),
            ], array_values($datos['margenes'] ?? [])));
        }

        return response()->json($this->respuesta());
    }

    /**
     * Los tramos de la tabla de precios de referencia, de menor a mayor costo
     * y sin repetirse; sólo el último puede quedar sin tope ("en adelante").
     */
    private function tramosEnOrden(): Closure
    {
        return function (string $atributo, mixed $tramos, Closure $fail): void {
            if (! is_array($tramos)) {
                return;
            }
            $anterior = 0.0;
            $tramos = array_values($tramos);
            foreach ($tramos as $i => $tramo) {
                $hasta = is_array($tramo) ? ($tramo['hasta'] ?? null) : null;
                if ($hasta === null || $hasta === '') {
                    if ($i < count($tramos) - 1) {
                        $fail('Sólo el último tramo puede quedar sin tope ("en adelante").');

                        return;
                    }

                    continue;
                }
                if (is_numeric($hasta) && (float) $hasta <= $anterior) {
                    $fail('Los tramos van de menor a mayor costo: cada "hasta" tiene que ser mayor que el anterior.');

                    return;
                }
                $anterior = is_numeric($hasta) ? (float) $hasta : $anterior;
            }
        };
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

        return response()->json($this->respuesta());
    }

    public function quitarLogo(): JsonResponse
    {
        if ($anterior = Empresa::logoRuta()) {
            Storage::disk('public')->delete($anterior);
        }
        Empresa::guardar('logo', null);

        return response()->json($this->respuesta());
    }

    /**
     * Lo de la empresa más los estados de la orden: el front los carga juntos
     * al iniciar sesión (nombres, íconos y colores de los chips).
     *
     * @return array<string, mixed>
     */
    private function respuesta(): array
    {
        return [...Empresa::datos(), 'estados_orden' => EstadoOrden::paraFront()];
    }
}
