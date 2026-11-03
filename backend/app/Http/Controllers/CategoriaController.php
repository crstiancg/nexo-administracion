<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoriaRequest;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoriaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->generateViewSetList(
            $request,
            Categoria::query()->with('padre:id,nombre')->withCount('hijos'),
            ['parent_id'],
            ['nombre'],
            ['id', 'nombre', 'parent_id'],
        );
    }

    public function store(StoreCategoriaRequest $request): JsonResponse
    {
        return response()->json(Categoria::create($request->validated('categoria')), 201);
    }

    public function show(Categoria $categoria): JsonResponse
    {
        return response()->json($categoria->load('padre:id,nombre'));
    }

    public function update(StoreCategoriaRequest $request, Categoria $categoria): JsonResponse
    {
        $categoria->update($request->validated('categoria'));

        return response()->json($categoria);
    }

    public function destroy(Categoria $categoria): JsonResponse|Response
    {
        // La FK es restrict: sin este chequeo el borrado explota con un 500.
        if ($categoria->hijos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene subcategorías. Elimínelas o muévalas primero.',
            ], 409);
        }

        if ($categoria->productos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene productos. Muévalos a otra categoría primero.',
            ], 409);
        }

        $categoria->delete();

        return response()->noContent();
    }
}
