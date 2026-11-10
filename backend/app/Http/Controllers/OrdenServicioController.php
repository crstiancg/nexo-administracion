<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrdenServicioRequest;
use App\Http\Resources\OrdenServicioResource;
use App\Models\EstadoOrden;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Services\OrdenesServicio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Las órdenes no se borran: un equipo que nunca se retiró pasa a un estado
 * "cerrado" (Abandonado). El cobro (abonos, comprobante) va por el pedido de la orden.
 */
class OrdenServicioController extends Controller
{
    public function __construct(private OrdenesServicio $ordenes) {}

    public function index(Request $request): JsonResponse
    {
        $query = OrdenServicio::query()
            ->with([
                'cliente:id,nombre,tipo_documento,numero_documento,telefono',
                'tecnico:id,name',
                'tipoEquipo:id,nombre',
                'marca:id,nombre',
                'pedido' => fn ($q) => $q->select('id', 'codigo', 'estado', 'total')->withSum('pagos', 'monto'),
            ]);

        // Por código, serie/IMEI, modelo, o nombre, documento o teléfono del cliente.
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('ordenes_servicio.codigo', 'like', $term)
                ->orWhere('ordenes_servicio.serie', 'like', $term)
                ->orWhere('ordenes_servicio.modelo', 'like', $term)
                ->orWhereHas('cliente', fn (Builder $c) => $c
                    ->where('nombre', 'like', $term)
                    ->orWhere('numero_documento', 'like', $term)
                    ->orWhere('telefono', 'like', $term)));
        }

        // "activas" = todo lo que sigue en el taller (sin entregar ni cerrar);
        // "listas" = terminadas, esperando que las recojan.
        $grupos = [
            'activas' => [EstadoOrden::INICIAL, EstadoOrden::EN_PROCESO, EstadoOrden::LISTO],
            'listas' => [EstadoOrden::LISTO],
        ];
        $grupo = $grupos[$request->input('estado')] ?? null;
        if ($grupo) {
            $query->whereIn('estado', EstadoOrden::clavesDe(...$grupo));
            $request->request->remove('estado');
            $request->query->remove('estado');
        }

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-id']);
        }

        return $this->generateViewSetList(
            $request,
            $query,
            ['estado', 'tecnico_id', 'cliente_id', 'sede_id', 'tipo_equipo_id'],
            [],
            ['id', 'estado', 'recibido_at'],
            OrdenServicioResource::class,
        );
    }

    /** Cuántas hay en cada estado (las tarjetas de arriba del listado). */
    public function resumen(Request $request): JsonResponse
    {
        $conteos = OrdenServicio::query()
            ->when($request->filled('sede_id'), fn ($q) => $q->where('sede_id', $request->integer('sede_id')))
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        // Los activos, y los ocultos sólo si todavía tienen órdenes.
        return response()->json(EstadoOrden::todos()
            ->filter(fn (EstadoOrden $e) => $e->activo || ($conteos[$e->clave] ?? 0) > 0)
            ->map(fn (EstadoOrden $e) => ['estado' => $e->clave, 'label' => $e->nombre, 'total' => (int) ($conteos[$e->clave] ?? 0)])
            ->values());
    }

    /** Los que pueden quedar a cargo de una orden (usuarios activos). */
    public function tecnicos(): JsonResponse
    {
        return response()->json(User::query()
            ->visiblesPara(request()->user())
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']));
    }

    public function store(StoreOrdenServicioRequest $request): JsonResponse
    {
        $orden = $this->ordenes->guardar(new OrdenServicio, $request->validated('orden'), $request->user());

        return response()->json($this->conDetalle($orden), 201);
    }

    public function show(OrdenServicio $orden): JsonResponse
    {
        return response()->json($this->conDetalle($orden));
    }

    public function update(StoreOrdenServicioRequest $request, OrdenServicio $orden): JsonResponse
    {
        $this->ordenes->guardar($orden, $request->validated('orden'), $request->user());

        return response()->json($this->conDetalle($orden));
    }

    /**
     * Enlaces firmados (unos minutos) para abrir la orden (A4 entera o partida
     * en dos copias) y la etiqueta
     * del equipo en una pestaña nueva, que no lleva el token de la sesión.
     */
    public function impresion(OrdenServicio $orden): JsonResponse
    {
        $vence = now()->addMinutes(config('empresa.impresion_minutos'));
        $enlace = fn (string $formato) => URL::temporarySignedRoute(
            'imprimir.orden-servicio', $vence, ['orden' => $orden->id, 'formato' => $formato],
        );

        return response()->json([
            'orden' => $enlace('orden'),
            'orden-doble' => $enlace('orden-doble'),
            'orden-premium' => $enlace('orden-premium'),
            'etiqueta' => $enlace('etiqueta'),
        ]);
    }

    public function estado(Request $request, OrdenServicio $orden): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(EstadoOrden::todos()->where('activo', true)->pluck('clave')->all())],
            'nota' => ['nullable', 'string', 'max:500'],
        ], [], ['estado' => 'estado', 'nota' => 'nota']);

        $orden = $this->ordenes->cambiarEstado($orden, $datos['estado'], $datos['nota'] ?? null, $request->user());

        return response()->json($this->conDetalle($orden));
    }

    /**
     * @return array<string, mixed>
     */
    private function conDetalle(OrdenServicio $orden): array
    {
        $orden->load([
            'cliente',
            'sede:id,nombre,direccion,telefono',
            'tecnico:id,name',
            'usuario:id,name',
            'tipoEquipo:id,nombre,checklist',
            'marca:id,nombre',
            'movimientos' => fn ($q) => $q->orderBy('id'),
            'movimientos.usuario:id,name',
            'archivos',
            'pedido',
            'pedido.items' => fn ($q) => $q->orderBy('id'),
            'pedido.items.variante' => fn ($q) => $q->conStockDeSede($orden->sede_id),
            'pedido.items.variante.producto:id,nombre,precio,categoria_id',
            'pedido.items.variante.unidad:id,nombre,abreviatura,fraccionable',
            'pedido.items.variante.color:id,nombre,hexadecimal',
            'pedido.pagos' => fn ($q) => $q->orderBy('id'),
            'pedido.pagos.usuario:id,name',
            'pedido.comprobantes' => fn ($q) => $q->latest('id'),
        ]);

        return (new OrdenServicioResource($orden))->resolve(request());
    }
}
