<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\Pago;
use App\Models\Pedido;
use App\Support\Fechas;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reportes de sólo lectura. Una venta cuenta desde que se CONFIRMA (ahí
 * descontó stock y congeló su costo): confirmados y entregados, por la fecha
 * de confirmación. `sede_id` vacío = todas las sedes.
 */
class ReporteController extends Controller
{
    /**
     * Totales, ganancia y desglose por día, sede, canal y método de pago.
     */
    public function ventas(Request $request): JsonResponse
    {
        [$desde, $hasta, $sedeId] = $this->filtros($request);

        $resumen = $this->pedidos($desde, $hasta, $sedeId)
            ->selectRaw('COUNT(*) as ventas, COALESCE(SUM(total), 0) as total, COALESCE(SUM(descuento), 0) as descuentos, COALESCE(SUM(op_gravada), 0) as op_gravada, COALESCE(SUM(igv), 0) as igv')
            ->first();

        // Costo congelado al confirmar. Los ítems sin costo (stock que entró
        // por un ajuste) no se pueden valorizar: se informan aparte.
        $costos = DB::table('pedido_items')
            ->joinSub($this->pedidos($desde, $hasta, $sedeId)->select('id'), 'p', 'p.id', '=', 'pedido_items.pedido_id')
            ->selectRaw('COALESCE(SUM(pedido_items.cantidad * pedido_items.costo_unitario), 0) as costo')
            ->selectRaw('SUM(CASE WHEN pedido_items.costo_unitario IS NULL THEN 1 ELSE 0 END) as sin_costo')
            ->first();

        $total = (float) $resumen->total;

        $porMetodo = DB::table('pagos')
            ->join('cajas', 'cajas.id', '=', 'pagos.caja_id')
            ->whereBetween('pagos.created_at', [$desde, $hasta])
            ->when($sedeId, fn (Builder $q) => $q->where('cajas.sede_id', $sedeId))
            ->groupBy('pagos.metodo')
            ->selectRaw('pagos.metodo, SUM(pagos.monto) as total, COUNT(*) as cantidad')
            ->get()
            ->map(fn ($fila) => [
                'metodo' => $fila->metodo,
                'label' => Pago::METODOS[$fila->metodo] ?? $fila->metodo,
                'total' => round((float) $fila->total, 2),
                'cantidad' => (int) $fila->cantidad,
            ])
            ->sortByDesc('total')
            ->values();

        return response()->json([
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'resumen' => [
                'ventas' => (int) $resumen->ventas,
                'total' => round($total, 2),
                'descuentos' => round((float) $resumen->descuentos, 2),
                // IGV incluido en las ventas del período (lo que se le debe a SUNAT).
                'op_gravada' => round((float) $resumen->op_gravada, 2),
                'igv' => round((float) $resumen->igv, 2),
                'ticket_promedio' => $resumen->ventas ? round($total / $resumen->ventas, 2) : 0,
                'costo' => round((float) $costos->costo, 2),
                'ganancia' => round($total - (float) $costos->costo, 2),
                'margen' => $total > 0 ? round(($total - (float) $costos->costo) / $total * 100, 1) : null,
                'items_sin_costo' => (int) $costos->sin_costo,
            ],
            'por_dia' => $this->pedidos($desde, $hasta, $sedeId)
                // El día local, no el UTC (una venta de las 20:00 en Lima es de hoy).
                ->selectRaw("DATE(DATE_ADD(confirmado_at, INTERVAL {$this->desfase()} MINUTE)) as fecha, COUNT(*) as ventas, SUM(total) as total")
                ->groupByRaw("DATE(DATE_ADD(confirmado_at, INTERVAL {$this->desfase()} MINUTE))")
                ->orderBy('fecha')
                ->get()
                ->map(fn ($f) => ['fecha' => (string) $f->fecha, 'ventas' => (int) $f->ventas, 'total' => round((float) $f->total, 2)]),
            'por_sede' => $this->pedidos($desde, $hasta, null)
                ->join('sedes', 'sedes.id', '=', 'pedidos.sede_id')
                ->selectRaw('sedes.id, sedes.nombre, COUNT(*) as ventas, SUM(pedidos.total) as total')
                ->groupBy('sedes.id', 'sedes.nombre')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre, 'ventas' => (int) $f->ventas, 'total' => round((float) $f->total, 2)]),
            'por_canal' => $this->pedidos($desde, $hasta, $sedeId)
                ->selectRaw('canal, COUNT(*) as ventas, SUM(total) as total')
                ->groupBy('canal')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($f) => ['canal' => $f->canal, 'label' => Pedido::CANALES[$f->canal] ?? $f->canal, 'ventas' => (int) $f->ventas, 'total' => round((float) $f->total, 2)]),
            'por_metodo' => $porMetodo,
        ]);
    }

    /**
     * Las presentaciones más vendidas por importe, con su ganancia.
     */
    public function productos(Request $request): JsonResponse
    {
        [$desde, $hasta, $sedeId] = $this->filtros($request);
        $limite = min(100, max(1, $request->integer('limite', 20)));

        $filas = DB::table('pedido_items')
            ->joinSub($this->pedidos($desde, $hasta, $sedeId)->select('id'), 'p', 'p.id', '=', 'pedido_items.pedido_id')
            ->join('variantes', 'variantes.id', '=', 'pedido_items.variante_id')
            ->join('productos', 'productos.id', '=', 'variantes.producto_id')
            ->join('unidades', 'unidades.id', '=', 'variantes.unidad_id')
            ->groupBy('variantes.id', 'variantes.sku', 'variantes.presentacion', 'productos.nombre', 'unidades.abreviatura')
            ->selectRaw('variantes.id, variantes.sku, variantes.presentacion, productos.nombre as producto, unidades.abreviatura as unidad')
            ->selectRaw('SUM(pedido_items.cantidad) as cantidad, SUM(pedido_items.subtotal) as importe')
            ->selectRaw('SUM(pedido_items.cantidad * pedido_items.costo_unitario) as costo')
            ->selectRaw('SUM(CASE WHEN pedido_items.costo_unitario IS NULL THEN 1 ELSE 0 END) as sin_costo')
            ->orderByDesc('importe')
            ->limit($limite)
            ->get();

        return response()->json([
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'data' => $filas->map(fn ($f) => [
                'variante_id' => $f->id,
                'sku' => $f->sku,
                'producto' => $f->producto,
                'presentacion' => $f->presentacion,
                'unidad' => $f->unidad,
                'cantidad' => round((float) $f->cantidad, 3),
                'importe' => round((float) $f->importe, 2),
                // Sin costo en algún ítem, la ganancia sería una mentira.
                'ganancia' => $f->sin_costo ? null : round((float) $f->importe - (float) $f->costo, 2),
            ]),
        ]);
    }

    /**
     * Inventario valorizado a costo promedio, por sede, y las presentaciones
     * que más capital inmovilizan.
     */
    public function inventario(Request $request): JsonResponse
    {
        $sedeId = $request->integer('sede_id') ?: null;

        $base = fn () => DB::table('stocks')
            ->join('variantes', 'variantes.id', '=', 'stocks.variante_id')
            ->where('stocks.cantidad', '>', 0)
            ->when($sedeId, fn (Builder $q) => $q->where('stocks.sede_id', $sedeId));

        $porSede = $base()
            ->join('sedes', 'sedes.id', '=', 'stocks.sede_id')
            ->groupBy('sedes.id', 'sedes.nombre')
            ->selectRaw('sedes.id, sedes.nombre, COUNT(*) as presentaciones')
            ->selectRaw('SUM(stocks.cantidad * COALESCE(variantes.costo_promedio, 0)) as valor')
            ->selectRaw('SUM(CASE WHEN variantes.costo_promedio IS NULL THEN 1 ELSE 0 END) as sin_costo')
            ->orderByDesc('valor')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'nombre' => $f->nombre,
                'presentaciones' => (int) $f->presentaciones,
                'valor' => round((float) $f->valor, 2),
                'sin_costo' => (int) $f->sin_costo,
            ]);

        // Aparte: lo agotado (cantidad 0) también está bajo el mínimo y la
        // consulta de arriba sólo mira lo que tiene stock.
        $bajoMinimo = DB::table('stocks')
            ->where('activo', true)
            ->where('stock_minimo', '>', 0)
            ->whereColumn('cantidad', '<', 'stock_minimo')
            ->when($sedeId, fn (Builder $q) => $q->where('sede_id', $sedeId))
            ->selectRaw('sede_id, COUNT(*) as total')
            ->groupBy('sede_id')
            ->pluck('total', 'sede_id');

        $porSede = $porSede->map(fn ($s) => [...$s, 'bajo_minimo' => (int) ($bajoMinimo[$s['id']] ?? 0)]);

        $top = $base()
            ->join('productos', 'productos.id', '=', 'variantes.producto_id')
            ->join('unidades', 'unidades.id', '=', 'variantes.unidad_id')
            ->groupBy('variantes.id', 'variantes.sku', 'variantes.presentacion', 'variantes.costo_promedio', 'productos.nombre', 'unidades.abreviatura')
            ->selectRaw('variantes.id, variantes.sku, variantes.presentacion, variantes.costo_promedio, productos.nombre as producto, unidades.abreviatura as unidad')
            ->selectRaw('SUM(stocks.cantidad) as cantidad, SUM(stocks.cantidad * COALESCE(variantes.costo_promedio, 0)) as valor')
            ->orderByDesc('valor')
            ->limit(20)
            ->get()
            ->map(fn ($f) => [
                'variante_id' => $f->id,
                'sku' => $f->sku,
                'producto' => $f->producto,
                'presentacion' => $f->presentacion,
                'unidad' => $f->unidad,
                'cantidad' => round((float) $f->cantidad, 3),
                'costo_promedio' => $f->costo_promedio !== null ? round((float) $f->costo_promedio, 2) : null,
                'valor' => round((float) $f->valor, 2),
            ]);

        $vencidos = Lote::query()
            ->join('variantes', 'variantes.id', '=', 'lotes.variante_id')
            ->where('lotes.cantidad', '>', 0)
            ->whereDate('lotes.vence_at', '<', Fechas::hoy())
            ->when($sedeId, fn ($q) => $q->where('lotes.sede_id', $sedeId))
            ->selectRaw('COUNT(*) as lotes, COALESCE(SUM(lotes.cantidad * COALESCE(variantes.costo_promedio, 0)), 0) as valor')
            ->first();

        return response()->json([
            'resumen' => [
                'valor' => round($porSede->sum('valor'), 2),
                'presentaciones' => $porSede->sum('presentaciones'),
                'sin_costo' => $porSede->sum('sin_costo'),
                'bajo_minimo' => (int) $bajoMinimo->sum(),
                'lotes_vencidos' => (int) $vencidos->lotes,
                'valor_vencido' => round((float) $vencidos->valor, 2),
            ],
            'por_sede' => $porSede,
            'top' => $top,
        ]);
    }

    /**
     * Minutos entre UTC y la zona del negocio (Lima: -300), para agrupar por
     * día local en SQL sin depender de las tablas de zonas de MySQL.
     */
    private function desfase(): int
    {
        return (int) now(Fechas::zona())->utcOffset();
    }

    /**
     * Rango (por defecto, el mes en curso) y sede.
     *
     * @return array{0: Carbon, 1: Carbon, 2: ?int}
     */
    private function filtros(Request $request): array
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'sede_id' => ['nullable', 'integer'],
        ]);

        return [
            // Días locales (Lima) pasados a UTC: así se guardan las fechas.
            Fechas::inicioDelDia($request->filled('desde') ? $request->date('desde') : Fechas::hoy()->startOfMonth()),
            Fechas::finDelDia($request->filled('hasta') ? $request->date('hasta') : null),
            $request->integer('sede_id') ?: null,
        ];
    }

    /**
     * Pedidos que cuentan como venta en el rango.
     */
    private function pedidos(Carbon $desde, Carbon $hasta, ?int $sedeId): Builder
    {
        return DB::table('pedidos')
            ->whereIn('pedidos.estado', [Pedido::CONFIRMADO, Pedido::ENTREGADO])
            ->whereBetween('pedidos.confirmado_at', [$desde, $hasta])
            ->when($sedeId, fn (Builder $q) => $q->where('pedidos.sede_id', $sedeId));
    }
}
