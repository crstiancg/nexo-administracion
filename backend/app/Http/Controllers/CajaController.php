<?php

namespace App\Http\Controllers;

use App\Http\Requests\CajaRequest;
use App\Http\Resources\CajaResource;
use App\Models\Caja;
use App\Services\Cajas;
use App\Support\Permisos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Caja: abrir, cobrar (desde pedidos), ingresos/egresos y cierre con arqueo.
 * Una caja cerrada es historial: no se edita ni se reabre.
 */
class CajaController extends Controller
{
    public function __construct(private Cajas $cajas) {}

    /**
     * Historial de cajas, la más nueva primero.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-id']);
        }

        return $this->generateViewSetList(
            $request,
            Caja::query()->with(['sede:id,nombre', 'abiertaPor:id,name', 'cerradaPor:id,name']),
            ['estado', 'sede_id'],
            [],
            ['id'],
            CajaResource::class,
        );
    }

    /**
     * La caja abierta de la sede del usuario con sus totales, o
     * `{ caja: null }` si no hay.
     */
    public function actual(Request $request): JsonResponse
    {
        $caja = $this->cajas->actual($request->user()->sedeOperativa());

        return response()->json(['caja' => $caja ? $this->conDetalle($caja) : null]);
    }

    public function show(Caja $caja): JsonResponse
    {
        return response()->json($this->conDetalle($caja));
    }

    public function abrir(CajaRequest $request): JsonResponse
    {
        $caja = $this->cajas->abrir($request->user()->sedeOperativa(), (float) $request->validated('caja.monto_apertura'), $request->user());

        return response()->json($this->conDetalle($caja), 201);
    }

    public function cerrar(CajaRequest $request, Caja $caja): JsonResponse
    {
        $caja = $this->cajas->cerrar(
            $caja,
            (float) $request->validated('caja.monto_contado'),
            $request->validated('caja.observacion'),
            $request->user(),
        );

        return response()->json($this->conDetalle($caja));
    }

    public function movimientos(CajaRequest $request): JsonResponse
    {
        $movimiento = $request->validated('movimiento');
        $this->cajas->movimiento($movimiento['tipo'], (float) $movimiento['monto'], $movimiento['concepto'], $request->user());

        return response()->json($this->conDetalle($this->cajas->actual($request->user()->sedeOperativa())), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function conDetalle(Caja $caja): array
    {
        $caja->load([
            'sede:id,nombre',
            'abiertaPor:id,name',
            'cerradaPor:id,name',
            'pagos' => fn ($q) => $q->latest('id'),
            'pagos.pedido:id,codigo',
            'pagos.usuario:id,name',
            'movimientos' => fn ($q) => $q->latest('id'),
            'movimientos.usuario:id,name',
        ]);

        $datos = (new CajaResource($caja))->conResumen($this->cajas->resumen($caja))->resolve(request());

        // La ganancia muestra costos: sólo a quien ve reportes o administra productos.
        $user = request()->user();
        if (Permisos::puede($user, 'reportes.ventas') || Permisos::puede($user, 'productos.update')) {
            $datos['ventas_turno'] = $this->cajas->ventasDelTurno($caja);
        }

        return $datos;
    }
}
