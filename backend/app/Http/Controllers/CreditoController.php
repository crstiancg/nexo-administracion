<?php

namespace App\Http\Controllers;

use App\Http\Resources\PagoResource;
use App\Models\Credito;
use App\Models\Pedido;
use App\Services\Creditos;
use App\Support\Fechas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

/**
 * Créditos a cuotas: lo que te deben los clientes. Se cobran como cualquier
 * pedido (api/pedidos/{pedido}/pagos), contra la caja abierta.
 */
class CreditoController extends Controller
{
    public function __construct(private Creditos $creditos) {}

    /**
     * estado: activos (con saldo, por defecto) | vencidos (con alguna cuota
     * vencida) | pagados | todos. Los totales son del filtro completo.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'estado' => ['nullable', Rule::in(['activos', 'por_vencer', 'vencidos', 'pagados', 'todos'])],
        ]);

        $creditos = Credito::query()
            ->with(['cliente:id,nombre,telefono,tipo_documento,numero_documento', 'pedido' => fn ($q) => $q->select('id', 'codigo', 'canal', 'total', 'estado')->withSum('pagos', 'monto'), 'cuotas'])
            ->when($request->filled('sede_id'), fn (Builder $q) => $q->where('sede_id', $request->integer('sede_id')))
            ->when($request->filled('search'), fn (Builder $q) => $q->whereHas('cliente', fn (Builder $c) => $c
                ->where('nombre', 'like', '%'.$request->input('search').'%')
                ->orWhere('numero_documento', 'like', '%'.$request->input('search').'%')))
            ->latest('id')
            ->get()
            ->map(fn (Credito $c) => $this->fila($c));

        $estado = $request->input('estado', 'activos');
        $filtrados = $creditos->filter(fn (array $c) => match ($estado) {
            'activos' => (float) $c['saldo'] > 0,
            'por_vencer' => Credito::proximaPorVencer($c['proxima']),
            'vencidos' => $c['cuotas_vencidas'] > 0,
            'pagados' => (float) $c['saldo'] <= 0,
            default => true,
        })->values();

        $porPagina = max(1, min(100, $request->integer('rowsPerPage', 20)));
        $pagina = max(1, $request->integer('page', 1));

        return response()->json([
            ...(new LengthAwarePaginator($filtrados->forPage($pagina, $porPagina)->values(), $filtrados->count(), $porPagina, $pagina))->toArray(),
            'resumen' => [
                'por_cobrar' => round($creditos->sum(fn ($c) => (float) $c['saldo']), 2),
                'vencido' => round($creditos->sum(fn ($c) => (float) $c['monto_vencido']), 2),
                'activos' => $creditos->filter(fn ($c) => (float) $c['saldo'] > 0)->count(),
                'con_vencidas' => $creditos->filter(fn ($c) => $c['cuotas_vencidas'] > 0)->count(),
                'por_vencer' => $creditos->filter(fn ($c) => Credito::proximaPorVencer($c['proxima']))->count(),
            ],
        ]);
    }

    public function show(Credito $credito): JsonResponse
    {
        $credito->load([
            'cliente', 'usuario:id,name', 'cuotas',
            'pedido' => fn ($q) => $q->withSum('pagos', 'monto'),
            'pedido.pagos' => fn ($q) => $q->orderBy('id'),
            'pedido.pagos.usuario:id,name',
            'pedido.ordenServicio:id,codigo,pedido_id',
        ]);

        return response()->json([
            ...$this->fila($credito),
            'cliente' => $credito->cliente,
            'usuario' => $credito->usuario?->only(['id', 'name']),
            'observacion' => $credito->observacion,
            'pedido_saldo' => number_format((float) $credito->pedido->total - (float) $credito->pedido->pagos_sum_monto, 2, '.', ''),
            // Todos los pagos del pedido (también el adelanto previo al crédito).
            'pagos' => PagoResource::collection($credito->pedido->pagos)->resolve(request()),
            'orden' => $credito->pedido->ordenServicio?->only(['id', 'codigo']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'credito.pedido_id' => ['required', 'integer', 'exists:pedidos,id'],
            'credito.frecuencia' => ['required', Rule::in(array_keys(Credito::FRECUENCIAS))],
            'credito.numero_cuotas' => ['required', 'integer', 'min:1', 'max:52'],
            'credito.primer_vencimiento' => ['required', 'date', 'after_or_equal:'.Fechas::hoyIso()],
            'credito.observacion' => ['nullable', 'string', 'max:250'],
        ], [
            'credito.primer_vencimiento.after_or_equal' => 'La primera cuota no puede vencer en el pasado.',
        ], [
            'credito.frecuencia' => 'frecuencia',
            'credito.numero_cuotas' => 'número de cuotas',
            'credito.primer_vencimiento' => 'primer vencimiento',
        ])['credito'];

        $credito = $this->creditos->otorgar(Pedido::query()->findOrFail($datos['pedido_id']), $datos, $request->user());

        return $this->show($credito)->setStatusCode(201);
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(Credito $credito): array
    {
        $pagadoPedido = round((float) ($credito->pedido->pagos_sum_monto ?? $credito->pedido->pagado()), 2);
        $cuotas = $credito->estadoCuotas($pagadoPedido);
        $vencidas = array_filter($cuotas, fn ($c) => $c['estado'] === 'vencida');
        $proxima = collect($cuotas)->first(fn ($c) => $c['estado'] !== 'pagada');

        return [
            'id' => $credito->id,
            'cliente' => $credito->cliente?->only(['id', 'nombre', 'telefono', 'tipo_documento', 'numero_documento']),
            'pedido' => $credito->pedido->only(['id', 'codigo', 'canal', 'estado']),
            'frecuencia' => $credito->frecuencia,
            'frecuencia_label' => Credito::FRECUENCIAS[$credito->frecuencia] ?? $credito->frecuencia,
            'numero_cuotas' => $credito->numero_cuotas,
            'monto' => $credito->monto,
            'pagado' => number_format($credito->pagado($pagadoPedido), 2, '.', ''),
            'saldo' => number_format($credito->saldo($pagadoPedido), 2, '.', ''),
            'cuotas_pagadas' => count(array_filter($cuotas, fn ($c) => $c['estado'] === 'pagada')),
            'cuotas_vencidas' => count($vencidas),
            'monto_vencido' => number_format(array_sum(array_map(fn ($c) => (float) $c['falta'], $vencidas)), 2, '.', ''),
            'proxima' => $proxima,
            'cuotas' => $cuotas,
            'fecha' => $credito->created_at?->toIso8601String(),
        ];
    }
}
