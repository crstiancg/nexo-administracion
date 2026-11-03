<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Lote;
use App\Models\OrdenServicio;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\Alertas;
use App\Services\Cajas;
use App\Support\Fechas;
use App\Support\Permisos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Lo que junta varios módulos: el dashboard, la campana de alertas y la
 * búsqueda global. Las rutas sólo piden sesión (`permisos.libres`): cada
 * bloque se arma únicamente si el usuario puede entrar a su pantalla
 * (App\Support\Permisos), así un vendedor no ve lo que no le corresponde.
 */
class PanelController extends Controller
{
    public function __construct(private Alertas $alertas, private Cajas $cajas) {}

    /**
     * Resumen de la sede del usuario, del día (en hora de Lima).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $sedeId = $user->sedeOperativa();
        $puede = fn (string $ruta) => Permisos::puede($user, $ruta);
        $veVentas = $puede('reportes.ventas') || $puede('ventas.store');

        return response()->json([
            'sede' => $user->sede?->only(['id', 'nombre']),
            'ventas' => $veVentas && $sedeId ? $this->ventas($sedeId) : null,
            'caja' => $puede('cajas.actual') && $sedeId ? $this->caja($sedeId) : null,
            'pendientes' => $sedeId ? $this->pendientes($sedeId, $puede) : null,
            'inventario' => $sedeId && ($puede('inventario.index') || $puede('inventario.lotes')) ? $this->inventario($sedeId, $puede) : null,
            'top_productos' => $veVentas && $sedeId ? $this->topProductos($sedeId) : null,
            'ultimas_ventas' => $puede('pedidos.index') && $sedeId ? $this->ultimasVentas($sedeId) : null,
            'taller' => $puede('ordenes-servicio.index') && $sedeId ? $this->taller($sedeId) : null,
            'alertas' => $this->alertas->para($user),
        ]);
    }

    /**
     * La campana: lo que necesita atención.
     */
    public function alertas(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->alertas->para($request->user())]);
    }

    /**
     * Búsqueda global (Ctrl+K): pedidos por código, clientes por nombre o
     * documento, productos por nombre, SKU o código de barras, cotizaciones
     * por código. Hasta 5 de cada uno.
     */
    public function buscar(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']]);

        $user = $request->user();
        $puede = fn (string $ruta) => Permisos::puede($user, $ruta);
        $q = trim($request->string('q'));
        $term = '%'.$q.'%';
        $resultados = [];

        if ($puede('productos.index')) {
            $resultados['productos'] = Producto::query()
                ->with(['marca:id,nombre', 'portada'])
                ->where(fn (Builder $b) => $b
                    ->where('nombre', 'like', $term)
                    ->orWhereHas('variantes', fn (Builder $v) => $v->where('sku', 'like', $term)->orWhere('codigo_barras', 'like', $term)))
                ->orderBy('nombre')
                ->limit(5)
                ->get()
                ->map(fn (Producto $p) => [
                    'id' => $p->id,
                    'titulo' => $p->nombre,
                    'detalle' => $p->marca?->nombre,
                    'imagen' => $p->portada ? Storage::disk('public')->url($p->portada->miniatura ?? $p->portada->ruta) : null,
                    'to' => "/productos/{$p->id}",
                ]);
        }

        if ($puede('pedidos.index')) {
            $resultados['pedidos'] = Pedido::query()
                ->with('cliente:id,nombre')
                ->where(fn (Builder $b) => $b
                    ->where('codigo', 'like', $term)
                    ->orWhereHas('cliente', fn (Builder $c) => $c->where('nombre', 'like', $term)->orWhere('numero_documento', 'like', $term)))
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Pedido $p) => [
                    'id' => $p->id,
                    'titulo' => $p->codigo,
                    'detalle' => ($p->cliente?->nombre ?? 'Cliente varios').' · S/ '.number_format((float) $p->total, 2).' · '.$p->estado,
                    'to' => "/pedidos?ver={$p->id}",
                ]);
        }

        if ($puede('clientes.index')) {
            $resultados['clientes'] = Cliente::query()
                ->where(fn (Builder $b) => $b->where('nombre', 'like', $term)->orWhere('numero_documento', 'like', $term))
                ->orderBy('nombre')
                ->limit(5)
                ->get()
                ->map(fn (Cliente $c) => [
                    'id' => $c->id,
                    'titulo' => $c->nombre,
                    'detalle' => trim(strtoupper((string) $c->tipo_documento).' '.$c->numero_documento),
                    'to' => '/clientes?search='.urlencode((string) ($c->numero_documento ?: $c->nombre)),
                ]);
        }

        if ($puede('cotizaciones.index')) {
            $resultados['cotizaciones'] = Cotizacion::query()
                ->with('cliente:id,nombre')
                ->where(fn (Builder $b) => $b
                    ->where('codigo', 'like', $term)
                    ->orWhereHas('cliente', fn (Builder $c) => $c->where('nombre', 'like', $term)))
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Cotizacion $c) => [
                    'id' => $c->id,
                    'titulo' => $c->codigo,
                    'detalle' => ($c->cliente?->nombre ?? 'Sin cliente').' · S/ '.number_format((float) $c->total, 2).' · '.$c->estadoVisible(),
                    'to' => "/cotizaciones?ver={$c->id}",
                ]);
        }

        return response()->json(['data' => $resultados]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ventas(int $sedeId): array
    {
        $hoy = Fechas::hoy();
        $resumen = fn (Carbon $desde, Carbon $hasta) => $this->pedidos($sedeId)
            ->whereBetween('confirmado_at', [$desde, $hasta])
            ->selectRaw('COUNT(*) as ventas, COALESCE(SUM(total), 0) as total, COALESCE(SUM(igv), 0) as igv')
            ->first();

        $deHoy = $resumen(Fechas::inicioDelDia(), Fechas::finDelDia());
        $deAyer = $resumen(Fechas::inicioDelDia($hoy->copy()->subDay()), Fechas::finDelDia($hoy->copy()->subDay()));
        $delMes = $resumen(Fechas::inicioDelDia($hoy->copy()->startOfMonth()), Fechas::finDelDia());

        // Últimos 14 días, con los días sin ventas en 0 (la serie no se corta).
        $desfase = (int) now(Fechas::zona())->utcOffset();
        $dia = "DATE(DATE_ADD(confirmado_at, INTERVAL {$desfase} MINUTE))";
        $porDia = $this->pedidos($sedeId)
            ->whereBetween('confirmado_at', [Fechas::inicioDelDia($hoy->copy()->subDays(13)), Fechas::finDelDia()])
            ->selectRaw("{$dia} as fecha, COUNT(*) as ventas, SUM(total) as total")
            ->groupByRaw($dia)
            ->get()
            ->keyBy(fn ($f) => (string) $f->fecha);

        $serie = collect(range(13, 0))->map(function (int $atras) use ($hoy, $porDia) {
            $fecha = $hoy->copy()->subDays($atras)->toDateString();

            return [
                'fecha' => $fecha,
                'ventas' => (int) ($porDia[$fecha]->ventas ?? 0),
                'total' => round((float) ($porDia[$fecha]->total ?? 0), 2),
            ];
        });

        // El mes anterior hasta el mismo día: comparar el mes en curso contra
        // el mes entero de antes siempre daría "baja" hasta el día 30.
        $inicioAnterior = $hoy->copy()->startOfMonth()->subMonthNoOverflow();
        $finAnterior = $inicioAnterior->copy()->addDays(min($hoy->day, $inicioAnterior->daysInMonth) - 1);
        $delMesAnterior = $resumen(Fechas::inicioDelDia($inicioAnterior), Fechas::finDelDia($finAnterior));

        $fila = fn ($r) => ['ventas' => (int) $r->ventas, 'total' => round((float) $r->total, 2), 'igv' => round((float) $r->igv, 2)];

        return [
            'hoy' => $fila($deHoy),
            'ayer' => $fila($deAyer),
            'mes' => $fila($delMes),
            'mes_anterior' => $fila($delMesAnterior),
            'por_dia' => $serie->values(),
            'por_mes' => $this->ventasPorMes($sedeId, $desfase),
            'por_canal' => $this->ventasPorCanal($sedeId),
        ];
    }

    /**
     * Ventas por mes: desde la primera venta (mínimo 12 meses, máximo 36),
     * con los meses sin ventas en 0. El front recorta 6M / 12M / Todo.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ventasPorMes(int $sedeId, int $desfase): array
    {
        $hoy = Fechas::hoy();
        $mes = "DATE_FORMAT(DATE_ADD(confirmado_at, INTERVAL {$desfase} MINUTE), '%Y-%m')";
        $primera = $this->pedidos($sedeId)->min('confirmado_at');
        $meses = $primera
            ? max(12, min(36, (int) Carbon::parse($primera)->startOfMonth()->diffInMonths($hoy->copy()->startOfMonth()) + 1))
            : 12;
        $desde = $hoy->copy()->startOfMonth()->subMonthsNoOverflow($meses - 1);

        $porMes = $this->pedidos($sedeId)
            ->where('confirmado_at', '>=', Fechas::inicioDelDia($desde))
            ->selectRaw("{$mes} as mes, COUNT(*) as ventas, SUM(total) as total")
            ->groupByRaw($mes)
            ->get()
            ->keyBy(fn ($f) => (string) $f->mes);

        return collect(range($meses - 1, 0))->map(function (int $atras) use ($hoy, $porMes) {
            $clave = $hoy->copy()->startOfMonth()->subMonthsNoOverflow($atras)->format('Y-m');

            return [
                'fecha' => "{$clave}-01",
                'ventas' => (int) ($porMes[$clave]->ventas ?? 0),
                'total' => round((float) ($porMes[$clave]->total ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * De dónde vinieron las ventas del mes (mostrador, WhatsApp, servicio
     * técnico…), con su participación.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ventasPorCanal(int $sedeId): array
    {
        $filas = $this->pedidos($sedeId)
            ->where('confirmado_at', '>=', Fechas::inicioDelDia(Fechas::hoy()->startOfMonth()))
            ->selectRaw('canal, COUNT(*) as ventas, SUM(total) as total')
            ->groupBy('canal')
            ->orderByDesc('total')
            ->get();
        $suma = (float) $filas->sum('total');

        return $filas->map(fn ($f) => [
            'canal' => $f->canal,
            'label' => Pedido::CANALES[$f->canal] ?? $f->canal,
            'ventas' => (int) $f->ventas,
            'total' => round((float) $f->total, 2),
            'porcentaje' => $suma > 0 ? round((float) $f->total * 100 / $suma, 1) : 0,
        ])->values()->all();
    }

    /**
     * El taller de la sede: qué hay, qué está listo, qué se está atrasando y
     * cuánto tarda un equipo en salir.
     *
     * @return array<string, mixed>
     */
    private function taller(int $sedeId): array
    {
        $ordenes = fn () => OrdenServicio::query()->where('sede_id', $sedeId);
        $activas = [OrdenServicio::RECIBIDO, OrdenServicio::DIAGNOSTICO, OrdenServicio::ESPERANDO_REPUESTO, OrdenServicio::EN_REPARACION];
        $hoy = Fechas::hoy();

        // Días promedio de recibido a entregado (últimos 30 días).
        $entregadas = $ordenes()
            ->where('estado', OrdenServicio::ENTREGADO)
            ->where('entregado_at', '>=', Fechas::inicioDelDia($hoy->copy()->subDays(29)))
            ->get(['recibido_at', 'entregado_at']);
        $promedio = $entregadas->isEmpty()
            ? null
            : round($entregadas->avg(fn ($o) => $o->recibido_at->diffInHours($o->entregado_at) / 24), 1);

        return [
            'en_taller' => $ordenes()->whereIn('estado', $activas)->count(),
            'listas' => $ordenes()->whereIn('estado', [OrdenServicio::REPARADO, OrdenServicio::SIN_REPARACION])->count(),
            'atrasadas' => $ordenes()->whereIn('estado', $activas)
                ->where('recibido_at', '<', Fechas::inicioDelDia($hoy->copy()->subDays(7)))->count(),
            'recibidas_hoy' => $ordenes()->where('recibido_at', '>=', Fechas::inicioDelDia())->count(),
            'entregadas_mes' => $ordenes()->where('estado', OrdenServicio::ENTREGADO)
                ->where('entregado_at', '>=', Fechas::inicioDelDia($hoy->copy()->startOfMonth()))->count(),
            'promedio_dias' => $promedio,
            // Carga por técnico: lo que cada uno tiene abierto.
            'por_tecnico' => OrdenServicio::query()
                ->where('ordenes_servicio.sede_id', $sedeId)
                ->whereIn('ordenes_servicio.estado', $activas)
                ->leftJoin('users', 'users.id', '=', 'ordenes_servicio.tecnico_id')
                ->selectRaw("COALESCE(users.name, 'Sin asignar') as tecnico, COUNT(*) as total")
                ->groupBy('users.name')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($f) => ['tecnico' => $f->tecnico, 'total' => (int) $f->total])
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function caja(int $sedeId): array
    {
        $caja = $this->cajas->actual($sedeId);
        if (! $caja) {
            return ['estado' => 'cerrada'];
        }

        $resumen = $this->cajas->resumen($caja);

        return [
            'estado' => $caja->esDeOtroDia() ? 'vencida' : 'abierta',
            'abierta_at' => $caja->abierta_at->toIso8601String(),
            'total_cobrado' => $resumen['total_cobrado'],
            'efectivo_esperado' => $resumen['efectivo_esperado'],
        ];
    }

    /**
     * @param  callable(string): bool  $puede
     * @return array<string, int|null>
     */
    private function pendientes(int $sedeId, callable $puede): array
    {
        $pedidos = $puede('pedidos.index');

        return [
            'pedidos_pendientes' => $pedidos ? Pedido::query()->where('sede_id', $sedeId)->where('estado', Pedido::PENDIENTE)->count() : null,
            'por_entregar' => $pedidos ? Pedido::query()->where('sede_id', $sedeId)->where('estado', Pedido::CONFIRMADO)->count() : null,
            'cotizaciones' => $puede('cotizaciones.index')
                ? Cotizacion::query()->where('sede_id', $sedeId)->where('estado', Cotizacion::PENDIENTE)->whereDate('valida_hasta', '>=', Fechas::hoy())->count()
                : null,
        ];
    }

    /**
     * @param  callable(string): bool  $puede
     * @return array<string, int|null>
     */
    private function inventario(int $sedeId, callable $puede): array
    {
        $lotes = Lote::query()->where('sede_id', $sedeId)->where('cantidad', '>', 0);
        $veLotes = $puede('inventario.lotes');

        return [
            'bajo_minimo' => $puede('inventario.index') ? $this->alertas->bajoMinimo($sedeId) : null,
            'lotes_vencidos' => $veLotes ? (clone $lotes)->whereDate('vence_at', '<', Fechas::hoy())->count() : null,
            'lotes_por_vencer' => $veLotes ? (clone $lotes)
                ->whereDate('vence_at', '>=', Fechas::hoy())
                ->whereDate('vence_at', '<=', Fechas::hoy()->addDays(Lote::DIAS_POR_VENCER))
                ->count() : null,
        ];
    }

    /**
     * Lo más vendido del mes en la sede, por importe.
     *
     * @return list<array<string, mixed>>
     */
    private function topProductos(int $sedeId): array
    {
        return DB::table('pedido_items')
            ->joinSub(
                $this->pedidos($sedeId)->whereBetween('confirmado_at', [Fechas::inicioDelDia(Fechas::hoy()->startOfMonth()), Fechas::finDelDia()])->select('id'),
                'p', 'p.id', '=', 'pedido_items.pedido_id',
            )
            ->join('variantes', 'variantes.id', '=', 'pedido_items.variante_id')
            ->join('productos', 'productos.id', '=', 'variantes.producto_id')
            ->join('unidades', 'unidades.id', '=', 'variantes.unidad_id')
            ->groupBy('productos.id', 'productos.nombre', 'variantes.presentacion', 'unidades.abreviatura')
            ->selectRaw('productos.id, productos.nombre, variantes.presentacion, unidades.abreviatura as unidad')
            ->selectRaw('SUM(pedido_items.cantidad) as cantidad, SUM(pedido_items.subtotal) as importe')
            ->orderByDesc('importe')
            ->limit(5)
            ->get()
            ->map(fn ($f) => [
                'producto_id' => $f->id,
                'nombre' => $f->nombre,
                'presentacion' => $f->presentacion,
                'unidad' => $f->unidad,
                'cantidad' => round((float) $f->cantidad, 3),
                'importe' => round((float) $f->importe, 2),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ultimasVentas(int $sedeId): array
    {
        return Pedido::query()
            ->with('cliente:id,nombre')
            ->where('sede_id', $sedeId)
            ->whereIn('estado', [Pedido::CONFIRMADO, Pedido::ENTREGADO])
            ->latest('confirmado_at')
            ->limit(6)
            ->get()
            ->map(fn (Pedido $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'cliente' => $p->cliente?->nombre ?? 'Cliente varios',
                'canal' => Pedido::CANALES[$p->canal] ?? $p->canal,
                'total' => $p->total,
                'fecha' => $p->confirmado_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Pedidos que cuentan como venta (mismo criterio que los reportes).
     */
    private function pedidos(int $sedeId): Builder
    {
        return Pedido::query()
            ->where('sede_id', $sedeId)
            ->whereIn('estado', [Pedido::CONFIRMADO, Pedido::ENTREGADO]);
    }
}
