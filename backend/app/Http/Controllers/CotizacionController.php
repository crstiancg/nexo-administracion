<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCotizacionRequest;
use App\Http\Resources\CotizacionResource;
use App\Models\Cotizacion;
use App\Models\User;
use App\Services\AsistenteCotizacion;
use App\Services\Cotizaciones;
use App\Services\Exportaciones;
use App\Support\Fechas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las cotizaciones no se borran: se rechazan o se convierten en pedido. Sólo
 * una pendiente se edita.
 */
class CotizacionController extends Controller
{
    /** "Por vencer": vence hoy o en los próximos días. */
    public const POR_VENCER_DIAS = 2;

    public function __construct(private Cotizaciones $cotizaciones) {}

    /**
     * Filtros: búsqueda, estado (más "por_vencer": pendientes que vencen en
     * POR_VENCER_DIAS días o menos), en qué se convirtió (`convertida_en`:
     * pedido | os), tipo (normal | pc), sede, quién la hizo (`user_id`) y
     * rango de fechas (días locales: `desde` / `hasta`).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'convertida_en' => ['nullable', 'in:pedido,os'],
        ]);

        $query = Cotizacion::query()
            ->with(['cliente:id,nombre,tipo_documento,numero_documento', 'sede:id,nombre', 'pedido:id,codigo', 'ordenServicio:id,codigo'])
            ->withCount('items');

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('cotizaciones.codigo', 'like', $term)
                ->orWhereHas('cliente', fn (Builder $c) => $c
                    ->where('nombre', 'like', $term)
                    ->orWhere('numero_documento', 'like', $term)));
        }

        // "vencida" no es una columna: pendiente con la validez pasada.
        if ($request->input('estado') === 'vencida') {
            $query->where('estado', Cotizacion::PENDIENTE)->whereDate('valida_hasta', '<', Fechas::hoy());
        } elseif ($request->input('estado') === 'por_vencer') {
            // Para llamar al cliente antes de que se le venza.
            $query->where('estado', Cotizacion::PENDIENTE)
                ->whereDate('valida_hasta', '>=', Fechas::hoy())
                ->whereDate('valida_hasta', '<=', Fechas::hoy()->addDays(self::POR_VENCER_DIAS));
        } elseif ($request->input('estado') === Cotizacion::PENDIENTE) {
            $query->where('estado', Cotizacion::PENDIENTE)->whereDate('valida_hasta', '>=', Fechas::hoy());
        } elseif ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->input('convertida_en') === 'pedido') {
            $query->whereNotNull('cotizaciones.pedido_id');
        } elseif ($request->input('convertida_en') === 'os') {
            $query->whereNotNull('cotizaciones.orden_servicio_id');
        }

        if ($request->filled('desde')) {
            $query->where('cotizaciones.created_at', '>=', Fechas::inicioDelDia($request->date('desde')));
        }
        if ($request->filled('hasta')) {
            $query->where('cotizaciones.created_at', '<=', Fechas::finDelDia($request->date('hasta')));
        }

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-id']);
        }

        return $this->generateViewSetList(
            $request,
            $query,
            ['cliente_id', 'sede_id', 'tipo', 'user_id'],
            [],
            ['id', 'total', 'valida_hasta'],
            CotizacionResource::class,
        );
    }

    /**
     * Asistente: arma un BORRADOR a partir de lo que el vendedor escribe (no
     * guarda nada; el form lo carga para revisarlo).
     */
    public function asistente(Request $request, AsistenteCotizacion $asistente): JsonResponse
    {
        $datos = $request->validate(['texto' => ['required', 'string', 'min:5', 'max:2000']], [], ['texto' => 'pedido']);

        return response()->json($asistente->borrador($datos['texto'], $request->user()));
    }

    /** Para el filtro "Atendido por": quienes hicieron alguna cotización. */
    public function usuarios(): JsonResponse
    {
        return response()->json(User::query()
            ->whereIn('id', Cotizacion::query()->select('user_id')->whereNotNull('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']));
    }

    public function store(StoreCotizacionRequest $request): JsonResponse
    {
        $cotizacion = $this->cotizaciones->guardar(new Cotizacion, $request->validated('cotizacion'), $request->user());

        return response()->json($this->conDetalle($cotizacion), 201);
    }

    public function show(Cotizacion $cotizacion): JsonResponse
    {
        return response()->json($this->conDetalle($cotizacion));
    }

    public function update(StoreCotizacionRequest $request, Cotizacion $cotizacion): JsonResponse
    {
        if (! $cotizacion->editable()) {
            return response()->json(['message' => "Una cotización {$cotizacion->estado} ya no se edita."], 409);
        }

        $this->cotizaciones->guardar($cotizacion, $request->validated('cotizacion'), $request->user());

        return response()->json($this->conDetalle($cotizacion));
    }

    /**
     * Devuelve la cotización (ya convertida) con el pedido que nació.
     */
    public function convertir(Request $request, Cotizacion $cotizacion): JsonResponse
    {
        // Opcional: sólo lo que de verdad pide el cliente (sin `items`, todo).
        $datos = $request->validate([
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.variante_id' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:99999999.999'],
        ], [
            'items.min' => 'Elige al menos un producto.',
        ], [
            'items.*.cantidad' => 'cantidad',
        ]);

        $this->cotizaciones->convertir($cotizacion, $request->user(), $datos['items'] ?? null);

        return response()->json($this->conDetalle($cotizacion->refresh()));
    }

    /** La cotización en Excel, como el PDF. */
    public function excel(Cotizacion $cotizacion, Exportaciones $exportar): StreamedResponse
    {
        return $exportar->descargar($exportar->cotizacion($cotizacion), "{$cotizacion->codigo}.xlsx");
    }

    public function rechazar(Cotizacion $cotizacion): JsonResponse
    {
        return response()->json($this->conDetalle($this->cotizaciones->rechazar($cotizacion)));
    }

    /**
     * @return array<string, mixed>
     */
    private function conDetalle(Cotizacion $cotizacion): array
    {
        $cotizacion->load([
            'cliente',
            'sede:id,nombre,direccion,telefono,horario',
            'usuario:id,name',
            'pedido:id,codigo,estado',
            'ordenServicio:id,codigo,estado',
            'items' => fn ($q) => $q->orderBy('id'),
            'items.variante.producto:id,nombre,precio,categoria_id',
            'items.variante.unidad:id,nombre,abreviatura,fraccionable',
            'items.variante.color:id,nombre,hexadecimal',
            'items.archivos',
        ]);

        return (new CotizacionResource($cotizacion))->resolve(request());
    }
}
