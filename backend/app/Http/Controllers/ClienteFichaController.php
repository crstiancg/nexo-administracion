<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Credito;
use App\Models\EstadoOrden;
use App\Models\OrdenServicio;
use App\Models\Pedido;
use App\Support\Permisos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La ficha del cliente: todo lo suyo en una pantalla (compras, órdenes de
 * servicio, cotizaciones y créditos) y cuánto debe. Cada sección sale sólo
 * si el usuario puede ver ese módulo.
 */
class ClienteFichaController extends Controller
{
    /** Lo último de cada cosa (la lista completa está en cada módulo). */
    public const ULTIMOS = 10;

    public function show(Request $request, Cliente $cliente): JsonResponse
    {
        $puede = fn (string $ruta) => Permisos::puede($request->user(), $ruta);

        // Lo comprado: pedidos confirmados o entregados (como los reportes).
        $ventas = Pedido::query()->where('cliente_id', $cliente->id)->whereIn('estado', [Pedido::CONFIRMADO, Pedido::ENTREGADO]);
        $conSaldo = (clone $ventas)->withSum('pagos', 'monto')->get(['id', 'total'])
            ->sum(fn (Pedido $p) => max(0, round((float) $p->total - (float) $p->pagos_sum_monto, 2)));

        return response()->json([
            'cliente' => (new ClienteResource($cliente))->resolve($request),
            'resumen' => [
                'compras' => (clone $ventas)->count(),
                'total_comprado' => round((float) (clone $ventas)->sum('total'), 2),
                'ultima_compra' => (clone $ventas)->max('confirmado_at'),
                // Lo que falta cobrar de sus compras (incluye lo financiado a crédito).
                'debe' => round($conSaldo, 2),
                'ordenes' => OrdenServicio::query()->where('cliente_id', $cliente->id)->count(),
                'cotizaciones' => Cotizacion::query()->where('cliente_id', $cliente->id)->count(),
            ],
            'pedidos' => $puede('pedidos.index') ? $this->pedidos($cliente) : null,
            'ordenes' => $puede('ordenes-servicio.index') ? $this->ordenes($cliente) : null,
            'cotizaciones' => $puede('cotizaciones.index') ? $this->cotizaciones($cliente) : null,
            'creditos' => $puede('creditos.index') ? $this->creditos($cliente) : null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function pedidos(Cliente $cliente): array
    {
        return Pedido::query()
            ->where('cliente_id', $cliente->id)
            ->withSum('pagos', 'monto')
            ->latest('id')
            ->limit(self::ULTIMOS)
            ->get()
            ->map(fn (Pedido $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'estado' => $p->estado,
                'canal' => Pedido::CANALES[$p->canal] ?? $p->canal,
                'total' => $p->total,
                // Sólo una venta hecha se debe (un pendiente todavía no se compró).
                'saldo' => in_array($p->estado, [Pedido::CONFIRMADO, Pedido::ENTREGADO], true)
                    ? number_format(max(0, (float) $p->total - (float) $p->pagos_sum_monto), 2, '.', '')
                    : '0.00',
                'fecha' => ($p->confirmado_at ?? $p->created_at)?->toIso8601String(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function ordenes(Cliente $cliente): array
    {
        return OrdenServicio::query()
            ->where('cliente_id', $cliente->id)
            ->with(['tipoEquipo:id,nombre', 'marca:id,nombre'])
            ->latest('id')
            ->limit(self::ULTIMOS)
            ->get()
            ->map(fn (OrdenServicio $o) => [
                'id' => $o->id,
                'codigo' => $o->codigo,
                'estado' => $o->estado,
                'estado_label' => EstadoOrden::nombreDe($o->estado),
                'equipo' => $o->equipo(),
                'falla' => $o->falla,
                'fecha' => $o->recibido_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function cotizaciones(Cliente $cliente): array
    {
        return Cotizacion::query()
            ->where('cliente_id', $cliente->id)
            ->latest('id')
            ->limit(self::ULTIMOS)
            ->get()
            ->map(fn (Cotizacion $c) => [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'estado' => $c->estadoVisible(),
                'total' => $c->total,
                'vistas' => (int) $c->vistas,
                'fecha' => $c->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function creditos(Cliente $cliente): array
    {
        return Credito::query()
            ->where('cliente_id', $cliente->id)
            ->with(['cuotas', 'pedido' => fn ($q) => $q->select('id', 'codigo', 'total')->withSum('pagos', 'monto')])
            ->latest('id')
            ->get()
            ->map(function (Credito $c) {
                $pagado = round((float) $c->pedido->pagos_sum_monto, 2);
                $cuotas = $c->estadoCuotas($pagado);

                return [
                    'id' => $c->id,
                    'pedido' => $c->pedido->codigo,
                    'monto' => $c->monto,
                    'saldo' => number_format($c->saldo($pagado), 2, '.', ''),
                    'cuotas_vencidas' => count(array_filter($cuotas, fn ($q) => $q['estado'] === 'vencida')),
                    'proxima' => collect($cuotas)->first(fn ($q) => $q['estado'] !== 'pagada'),
                ];
            })
            ->filter(fn (array $c) => (float) $c['saldo'] > 0)
            ->values()
            ->all();
    }
}
