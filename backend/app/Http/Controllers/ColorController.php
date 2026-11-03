<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreColorRequest;
use App\Models\Color;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ruta esperada en routes/api.php, dentro del grupo protegido:
 * Route::apiResource('colores', ColorController::class)->parameters(['colores' => 'color']);
 * (sin ->parameters el parámetro sale {colore}: Laravel singulariza en inglés).
 */
class ColorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->generateViewSetList(
            $request,
            Color::query(),
            [],
            ['nombre', 'hexadecimal'],
            ['id', 'nombre', 'hexadecimal'],
        );
    }

    public function store(StoreColorRequest $request): JsonResponse
    {
        return response()->json(Color::create($request->validated('color')), 201);
    }

    public function show(Color $color): JsonResponse
    {
        return response()->json($color);
    }

    public function update(StoreColorRequest $request, Color $color): JsonResponse
    {
        $color->update($request->validated('color'));

        return response()->json($color);
    }

    public function destroy(Color $color): JsonResponse|Response
    {
        // La FK de variantes es restrict: sin este chequeo sería un 500.
        if ($color->variantes()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay productos con este color.',
            ], 409);
        }

        $color->delete();

        return response()->noContent();
    }
}
