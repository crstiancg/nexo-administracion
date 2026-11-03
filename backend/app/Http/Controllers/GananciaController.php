<?php

namespace App\Http\Controllers;

use App\Services\Finanzas;
use App\Support\Fechas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La utilidad real del período (por defecto, el mes en curso).
 */
class GananciaController extends Controller
{
    public function __construct(private Finanzas $finanzas) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'sede_id' => ['nullable', 'integer'],
        ]);

        return response()->json($this->finanzas->ganancias(
            Fechas::inicioDelDia($request->filled('desde') ? $request->date('desde') : Fechas::hoy()->startOfMonth()),
            Fechas::finDelDia($request->filled('hasta') ? $request->date('hasta') : null),
            $request->integer('sede_id') ?: null,
        ));
    }
}
