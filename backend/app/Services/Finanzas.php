<?php

namespace App\Services;

use App\Models\ConceptoFinanciero;
use App\Models\EstadoOrden;
use App\Models\MovimientoCaja;
use App\Models\MovimientoFinanciero;
use App\Models\OrdenServicio;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use App\Support\Fechas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ingresos y gastos que no son ventas, y la utilidad real del período:
 * lo vendido y reparado, menos lo que costó, más otros ingresos, menos los
 * gastos. Mismo criterio de "venta" que los reportes (pedidos confirmados o
 * entregados, por la fecha de confirmación).
 */
class Finanzas
{
    public function __construct(private Cajas $cajas) {}

    /**
     * Si fue en efectivo y `de_caja`, sale (o entra) también del cajón: así el
     * arqueo cuadra. Eso exige la caja abierta y efectivo suficiente.
     *
     * @param  array<string, mixed>  $datos  validados
     */
    public function registrar(array $datos, User $usuario): MovimientoFinanciero
    {
        return DB::transaction(function () use ($datos, $usuario) {
            $concepto = ConceptoFinanciero::query()->findOrFail($datos['concepto_id']);
            $movimiento = new MovimientoFinanciero([...$datos, 'tipo' => $concepto->tipo]);
            $movimiento->forceFill([
                'sede_id' => $usuario->sedeOperativa(),
                'user_id' => $usuario->id,
            ]);

            if (($datos['de_caja'] ?? false) && $datos['metodo'] === Pago::EFECTIVO) {
                $caja = $this->cajas->movimiento(
                    $concepto->tipo === ConceptoFinanciero::GASTO ? MovimientoCaja::EGRESO : MovimientoCaja::INGRESO,
                    (float) $datos['monto'],
                    mb_substr(trim($concepto->nombre.(($datos['descripcion'] ?? null) ? ': '.$datos['descripcion'] : '')), 0, 200),
                    $usuario,
                );
                $movimiento->forceFill(['movimiento_caja_id' => $caja->id]);
            }

            $movimiento->save();

            return $movimiento;
        });
    }

    public function eliminar(MovimientoFinanciero $movimiento): void
    {
        // Lo que pasó por el cajón ya está en el arqueo: no se deshace.
        if ($movimiento->salioDeCaja()) {
            throw ValidationException::withMessages([
                'movimiento' => 'Este movimiento salió de la caja y ya está en su arqueo: no se puede eliminar. Registra el movimiento contrario.',
            ])->status(409);
        }

        $movimiento->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function ganancias(Carbon $desde, Carbon $hasta, ?int $sedeId): array
    {
        $pedidos = fn () => DB::table('pedidos')
            ->whereIn('pedidos.estado', [Pedido::CONFIRMADO, Pedido::ENTREGADO])
            ->whereBetween('pedidos.confirmado_at', [$desde, $hasta])
            ->when($sedeId, fn ($q) => $q->where('pedidos.sede_id', $sedeId));

        // Ventas de mostrador y cobros de servicio técnico, por separado.
        $porOrigen = $pedidos()
            ->selectRaw("CASE WHEN canal = ? THEN 'reparaciones' ELSE 'ventas' END as origen", [Pedido::CANAL_SERVICIO])
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, COALESCE(SUM(igv), 0) as igv')
            ->groupBy('origen')
            ->get()
            ->keyBy('origen');

        $costos = DB::table('pedido_items')
            ->joinSub($pedidos()->select('id'), 'p', 'p.id', '=', 'pedido_items.pedido_id')
            ->selectRaw('COALESCE(SUM(pedido_items.cantidad * pedido_items.costo_unitario), 0) as costo')
            ->selectRaw('SUM(CASE WHEN pedido_items.costo_unitario IS NULL THEN 1 ELSE 0 END) as sin_costo')
            ->first();

        $movimientos = MovimientoFinanciero::query()
            ->whereBetween('fecha', [$desde->copy()->setTimezone(Fechas::zona())->toDateString(), $hasta->copy()->setTimezone(Fechas::zona())->toDateString()])
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId));

        $porConcepto = (clone $movimientos)
            ->join('conceptos_financieros', 'conceptos_financieros.id', '=', 'movimientos_financieros.concepto_id')
            ->selectRaw('movimientos_financieros.tipo, conceptos_financieros.nombre as concepto, COUNT(*) as cantidad, SUM(movimientos_financieros.monto) as total')
            ->groupBy('movimientos_financieros.tipo', 'conceptos_financieros.nombre')
            ->orderByDesc('total')
            ->get();

        $ventas = round((float) ($porOrigen['ventas']->total ?? 0), 2);
        $reparaciones = round((float) ($porOrigen['reparaciones']->total ?? 0), 2);
        $costo = round((float) $costos->costo, 2);
        $otrosIngresos = round((float) $porConcepto->where('tipo', ConceptoFinanciero::INGRESO)->sum('total'), 2);
        $gastos = round((float) $porConcepto->where('tipo', ConceptoFinanciero::GASTO)->sum('total'), 2);
        $ingresos = round($ventas + $reparaciones + $otrosIngresos, 2);
        $utilidad = round($ingresos - $costo - $gastos, 2);

        $detalle = fn (string $tipo) => $porConcepto->where('tipo', $tipo)->map(fn ($f) => [
            'concepto' => $f->concepto,
            'cantidad' => (int) $f->cantidad,
            'total' => round((float) $f->total, 2),
        ])->values();

        return [
            'desde' => $desde->copy()->setTimezone(Fechas::zona())->toDateString(),
            'hasta' => $hasta->copy()->setTimezone(Fechas::zona())->toDateString(),
            'ventas' => ['total' => $ventas, 'cantidad' => (int) ($porOrigen['ventas']->cantidad ?? 0)],
            'reparaciones' => ['total' => $reparaciones, 'cantidad' => (int) ($porOrigen['reparaciones']->cantidad ?? 0)],
            'otros_ingresos' => $otrosIngresos,
            'ingresos' => $ingresos,
            'costo' => $costo,
            'items_sin_costo' => (int) $costos->sin_costo,
            'gastos' => $gastos,
            'utilidad' => $utilidad,
            'margen' => $ingresos > 0 ? round($utilidad / $ingresos * 100, 1) : null,
            // IGV cobrado dentro de las ventas: se le debe a SUNAT (referencia).
            'igv' => round((float) $porOrigen->sum('igv'), 2),
            'gastos_por_concepto' => $detalle(ConceptoFinanciero::GASTO),
            'ingresos_por_concepto' => $detalle(ConceptoFinanciero::INGRESO),
            'por_tecnico' => $this->porTecnico($desde, $hasta, $sedeId),
        ];
    }

    /**
     * Lo que dejó cada técnico: órdenes entregadas en el período, lo cobrado
     * (mano de obra + repuestos) y la ganancia (menos el costo de los repuestos).
     *
     * @return array<int, array<string, mixed>>
     */
    private function porTecnico(Carbon $desde, Carbon $hasta, ?int $sedeId): array
    {
        $costoPorPedido = DB::table('pedido_items')
            ->selectRaw('pedido_id, SUM(cantidad * COALESCE(costo_unitario, 0)) as costo')
            ->groupBy('pedido_id');

        return OrdenServicio::query()
            ->from('ordenes_servicio')
            ->where('ordenes_servicio.estado', EstadoOrden::entregado())
            ->whereBetween('ordenes_servicio.entregado_at', [$desde, $hasta])
            ->when($sedeId, fn ($q) => $q->where('ordenes_servicio.sede_id', $sedeId))
            ->join('pedidos', 'pedidos.id', '=', 'ordenes_servicio.pedido_id')
            ->leftJoinSub($costoPorPedido, 'c', 'c.pedido_id', '=', 'pedidos.id')
            ->leftJoin('users', 'users.id', '=', 'ordenes_servicio.tecnico_id')
            ->selectRaw("COALESCE(users.name, 'Sin asignar') as tecnico")
            ->selectRaw('COUNT(*) as ordenes')
            ->selectRaw('SUM(ordenes_servicio.mano_obra) as mano_obra')
            ->selectRaw('SUM(pedidos.total) as cobrado')
            ->selectRaw('SUM(pedidos.total - COALESCE(c.costo, 0)) as ganancia')
            ->groupBy('users.name')
            ->orderByDesc('ganancia')
            ->get()
            ->map(fn ($f) => [
                'tecnico' => $f->tecnico,
                'ordenes' => (int) $f->ordenes,
                'mano_obra' => round((float) $f->mano_obra, 2),
                'cobrado' => round((float) $f->cobrado, 2),
                'ganancia' => round((float) $f->ganancia, 2),
            ])
            ->values()
            ->all();
    }
}
