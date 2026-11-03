<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSedeRequest;
use App\Models\Caja;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SedeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->generateViewSetList(
            $request,
            Sede::query()->withCount('usuarios'),
            ['activo'],
            ['nombre', 'direccion'],
            ['id', 'nombre', 'activo'],
        );
    }

    public function store(StoreSedeRequest $request): JsonResponse
    {
        return response()->json(Sede::create($request->validated('sede')), 201);
    }

    public function show(Sede $sede): JsonResponse
    {
        return response()->json($sede);
    }

    public function update(StoreSedeRequest $request, Sede $sede): JsonResponse
    {
        $sede->update($request->validated('sede'));

        return response()->json($sede);
    }

    /**
     * Sólo una sede sin historia: con stock, movimientos, ventas o cajas es
     * parte del historial (FK restrict) y se desactiva en su lugar.
     */
    public function destroy(Sede $sede): JsonResponse|Response
    {
        $usada = $sede->stocks()->exists()
            || MovimientoInventario::query()->where('sede_id', $sede->id)->exists()
            || Pedido::query()->where('sede_id', $sede->id)->exists()
            || Caja::query()->where('sede_id', $sede->id)->exists();

        if ($usada) {
            return response()->json([
                'message' => 'No se puede eliminar: la sede ya tiene stock, ventas o cajas. Desactívela en su lugar.',
            ], 409);
        }

        $sede->delete();

        return response()->noContent();
    }
}
