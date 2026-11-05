<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use App\Models\Variante;
use App\Services\Exportaciones;
use App\Services\Kardex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kardex valorizado de una presentación (para el contador y el inventario
 * anual): en pantalla y en Excel. Muestra costos: es de quien ve el valor
 * del inventario.
 */
class KardexController extends Controller
{
    public function __construct(private Kardex $kardex) {}

    public function show(Request $request): JsonResponse
    {
        [$variante, $sede, $datos] = $this->armar($request);

        return response()->json([
            'variante' => [
                'id' => $variante->id,
                'sku' => $variante->sku,
                'producto' => $variante->producto->nombre,
                'presentacion' => $variante->presentacion,
                'unidad' => $variante->unidad?->abreviatura,
            ],
            'sede' => $sede?->only(['id', 'nombre']),
            ...$datos,
        ]);
    }

    public function excel(Request $request, Exportaciones $exportar): StreamedResponse
    {
        [$variante, $sede, $datos] = $this->armar($request);

        return $exportar->descargar(
            $exportar->kardex($variante, $sede?->nombre, $request->input('desde'), $request->input('hasta'), $datos),
            "kardex-{$variante->sku}.xlsx",
        );
    }

    /** @return array{0: Variante, 1: ?Sede, 2: array<string, mixed>} */
    private function armar(Request $request): array
    {
        $request->validate([
            'variante_id' => ['required', 'integer', 'exists:variantes,id'],
            'sede_id' => ['nullable', 'integer', 'exists:sedes,id'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $variante = Variante::query()->with(['producto:id,nombre', 'unidad:id,abreviatura'])->findOrFail($request->integer('variante_id'));
        $sede = $request->filled('sede_id') ? Sede::query()->find($request->integer('sede_id')) : null;

        return [$variante, $sede, $this->kardex->de($variante, $sede?->id, $request->date('desde'), $request->date('hasta'))];
    }
}
