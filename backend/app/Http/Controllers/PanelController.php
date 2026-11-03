<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Lote;
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

        $fila = fn ($r) => ['ventas' => (int) $r->ventas, 'total' => round((float) $r->total, 2), 'igv' => round((float) $r->igv, 2)];

        return [
            'hoy' => $fila($deHoy),
            'ayer' => $fila($deAyer),
            'mes' => $fila($delMes),
            'por_dia' => $serie->values(),
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
