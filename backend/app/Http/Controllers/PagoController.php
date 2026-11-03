<?php

namespace App\Http\Controllers;

use App\Http\Requests\CobrarPedidoRequest;
use App\Http\Requests\DevolverPagoRequest;
use App\Http\Resources\PagoResource;
use App\Models\Pedido;
use App\Services\Cajas;
use Illuminate\Http\JsonResponse;

/**
 * Cobros y devoluciones de un pedido. Entran a la caja abierta.
 */
class PagoController extends Controller
{
    public function __construct(private Cajas $cajas) {}

    public function cobrar(CobrarPedidoRequest $request, Pedido $pedido): JsonResponse
    {
        $pago = $this->cajas->cobrar($pedido, $request->validated('pago'), $request->user());

        return response()->json((new PagoResource($pago))->resolve($request), 201);
    }

    public function devolver(DevolverPagoRequest $request, Pedido $pedido): JsonResponse
    {
        $pago = $this->cajas->devolver($pedido, $request->validated('pago'), $request->user());

        return response()->json((new PagoResource($pago))->resolve($request), 201);
    }
}
