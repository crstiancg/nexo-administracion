<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMarcaRequest;
use App\Models\Marca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MarcaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->generateViewSetList(
            $request,
            Marca::query()->withCount('productos'),
            ['activo'],
            ['nombre'],
            ['id', 'nombre', 'activo'],
        );
    }

    public function store(StoreMarcaRequest $request): JsonResponse
    {
        return response()->json(Marca::create($request->validated('marca')), 201);
    }

    public function show(Marca $marca): JsonResponse
    {
        return response()->json($marca);
    }

    public function update(StoreMarcaRequest $request, Marca $marca): JsonResponse
    {
        $marca->update($request->validated('marca'));

        return response()->json($marca);
    }

    public function destroy(Marca $marca): JsonResponse|Response
    {
        // La FK de productos es restrict: sin este chequeo sería un 500.
        if ($marca->productos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay productos de esta marca. Desactívela en su lugar.',
            ], 409);
        }

        $marca->delete();

        return response()->noContent();
    }
}
