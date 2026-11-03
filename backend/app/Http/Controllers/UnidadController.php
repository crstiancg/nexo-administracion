<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnidadRequest;
use App\Models\Unidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ruta en routes/api.php con ->parameters(['unidades' => 'unidad']): sin eso
 * el parámetro sale {unidade} (Laravel singulariza en inglés).
 */
class UnidadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->generateViewSetList(
            $request,
            Unidad::query(),
            ['fraccionable'],
            ['nombre', 'abreviatura'],
            ['id', 'nombre', 'abreviatura'],
        );
    }

    public function store(StoreUnidadRequest $request): JsonResponse
    {
        return response()->json(Unidad::create($request->validated('unidad')), 201);
    }

    public function show(Unidad $unidad): JsonResponse
    {
        return response()->json($unidad);
    }

    public function update(StoreUnidadRequest $request, Unidad $unidad): JsonResponse
    {
        $unidad->update($request->validated('unidad'));

        return response()->json($unidad);
    }

    public function destroy(Unidad $unidad): JsonResponse|Response
    {
        // La FK de variantes es restrict: sin este chequeo sería un 500.
        if ($unidad->variantes()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay presentaciones que usan esta unidad.',
            ], 409);
        }

        $unidad->delete();

        return response()->noContent();
    }
}
