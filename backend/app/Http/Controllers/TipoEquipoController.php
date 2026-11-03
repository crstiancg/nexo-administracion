<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTipoEquipoRequest;
use App\Models\TipoEquipo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TipoEquipoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->generateViewSetList(
            $request,
            TipoEquipo::query()->withCount('ordenes'),
            ['activo'],
            ['nombre'],
            ['id', 'nombre', 'activo'],
        );
    }

    public function store(StoreTipoEquipoRequest $request): JsonResponse
    {
        return response()->json(TipoEquipo::create($request->validated('tipo')), 201);
    }

    public function show(TipoEquipo $tiposEquipo): JsonResponse
    {
        return response()->json($tiposEquipo);
    }

    public function update(StoreTipoEquipoRequest $request, TipoEquipo $tiposEquipo): JsonResponse
    {
        $tiposEquipo->update($request->validated('tipo'));

        return response()->json($tiposEquipo);
    }

    public function destroy(TipoEquipo $tiposEquipo): JsonResponse|Response
    {
        // La FK de órdenes es restrict: sin este chequeo sería un 500.
        if ($tiposEquipo->ordenes()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay órdenes con este tipo de equipo. Desactívelo en su lugar.',
            ], 409);
        }

        $tiposEquipo->delete();

        return response()->noContent();
    }
}
