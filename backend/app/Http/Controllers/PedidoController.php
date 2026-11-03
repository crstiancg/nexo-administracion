<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePedidoRequest;
use App\Http\Resources\PedidoResource;
use App\Models\Pedido;
use App\Services\Exportaciones;
use App\Services\Pedidos;
use App\Support\Fechas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Los pedidos no se borran: se cancelan (son historial de ventas). Sólo un
 * pedido pendiente se edita; los cambios de estado van por App\Services\Pedidos.
 */
class PedidoController extends Controller
{
    public function __construct(private Pedidos $pedidos) {}

    public function index(Request $request): JsonResponse
    {
        $query = Pedido::query()->with(['cliente', 'sede:id,nombre'])->withCount('items')->withSum('pagos', 'monto');
        $this->filtrar($request, $query);

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-id']);
        }

        $respuesta = $this->generateViewSetList(
            $request,
            $query,
            ['estado', 'canal', 'cliente_id', 'sede_id'],
            [],
            ['id', 'total', 'estado'],
            PedidoResource::class,
        )->getData(true);

        // Lo que espera a alguien: sin confirmar y confirmados sin entregar
        // (en la sede filtrada o en todas; no depende de la página).
        $atencion = Pedido::query()
            ->when($request->filled('sede_id'), fn (Builder $q) => $q->where('sede_id', $request->integer('sede_id')))
            ->whereIn('estado', [Pedido::PENDIENTE, Pedido::CONFIRMADO])
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');
        $respuesta['atencion'] = [
            'pendientes' => (int) ($atencion[Pedido::PENDIENTE] ?? 0),
            'por_entregar' => (int) ($atencion[Pedido::CONFIRMADO] ?? 0),
        ];

        return response()->json($respuesta);
    }

    /** Los pedidos del filtro (el mismo del listado) en Excel. */
    public function exportar(Request $request, Exportaciones $exportar): StreamedResponse
    {
        $query = Pedido::query()->with(['cliente:id,nombre,tipo_documento,numero_documento', 'sede:id,nombre', 'usuario:id,name'])->withSum('pagos', 'monto');
        $this->filtrar($request, $query);
        foreach (['estado', 'canal', 'cliente_id', 'sede_id'] as $campo) {
            if ($request->filled($campo)) {
                $query->where("pedidos.{$campo}", $request->input($campo));
            }
        }

        $pedidos = $query->orderByDesc('id')->limit(5000)->get();
        $periodo = $request->filled('desde') ? ' '.$request->input('desde').' a '.$request->input('hasta', Fechas::hoyIso()) : '';

        return $exportar->descargar($exportar->pedidos($pedidos, 'Pedidos'.$periodo), 'pedidos'.str_replace(' ', '_', $periodo).'.xlsx');
    }

    /**
     * Búsqueda (código, nombre o documento del cliente) y rango de fechas
     * (días locales: `desde` / `hasta` en YYYY-MM-DD).
     */
    private function filtrar(Request $request, Builder $query): void
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('pedidos.codigo', 'like', $term)
                ->orWhereHas('cliente', fn (Builder $c) => $c
                    ->where('nombre', 'like', $term)
                    ->orWhere('numero_documento', 'like', $term)));
        }

        if ($request->filled('desde')) {
            $query->where('pedidos.created_at', '>=', Fechas::inicioDelDia($request->date('desde')));
        }
        if ($request->filled('hasta')) {
            $query->where('pedidos.created_at', '<=', Fechas::finDelDia($request->date('hasta')));
        }
    }

    public function store(StorePedidoRequest $request): JsonResponse
    {
        $pedido = $this->pedidos->guardar(new Pedido, $request->validated('pedido'), $request->user());

        return response()->json($this->conDetalle($pedido), 201);
    }

    public function show(Pedido $pedido): JsonResponse
    {
        return response()->json($this->conDetalle($pedido));
    }

    public function update(StorePedidoRequest $request, Pedido $pedido): JsonResponse
    {
        $this->noDeServicio($pedido);

        if (! $pedido->editable()) {
            return response()->json([
                'message' => "Un pedido {$pedido->estado} ya no se edita.",
            ], 409);
        }

        $this->pedidos->guardar($pedido, $request->validated('pedido'), $request->user());

        return response()->json($this->conDetalle($pedido));
    }

    public function confirmar(Request $request, Pedido $pedido): JsonResponse
    {
        $this->noDeServicio($pedido);

        return response()->json($this->conDetalle($this->pedidos->confirmar($pedido, $request->user())));
    }

    public function entregar(Pedido $pedido): JsonResponse
    {
        $this->noDeServicio($pedido);

        return response()->json($this->conDetalle($this->pedidos->entregar($pedido)));
    }

    public function cancelar(Request $request, Pedido $pedido): JsonResponse
    {
        $this->noDeServicio($pedido);

        return response()->json($this->conDetalle($this->pedidos->cancelar($pedido, $request->user())));
    }

    /** Anula una venta ya hecha: vuelven stock y plata. Pide el motivo. */
    public function anular(Request $request, Pedido $pedido): JsonResponse
    {
        $this->noDeServicio($pedido);

        $motivo = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
        ], [], ['motivo' => 'motivo'])['motivo'];

        return response()->json($this->conDetalle($this->pedidos->anular($pedido, trim($motivo), $request->user())));
    }

    /**
     * El pedido de una orden de servicio se edita, se confirma y se entrega
     * desde la orden (que descuenta los repuestos al entregar el equipo). Acá
     * sólo se cobra y se emite el comprobante.
     */
    private function noDeServicio(Pedido $pedido): void
    {
        if ($pedido->esDeServicio()) {
            throw ValidationException::withMessages([
                'estado' => 'Este pedido es el cobro de una orden de servicio: se gestiona desde la orden.',
            ])->status(409);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function conDetalle(Pedido $pedido): array
    {
        $pedido->load([
            'cliente',
            // Con dirección y teléfono: salen en el ticket que se reimprime.
            'sede:id,nombre,direccion,telefono',
            'usuario:id,name',
            'anuladoPor:id,name',
            'items' => fn ($q) => $q->orderBy('id'),
            // Stock de la sede del pedido (no el total): es de donde sale al
            // confirmar, y el front avisa antes de cobrar o confirmar.
            'items.variante' => fn ($q) => $q->conStockDeSede($pedido->sede_id),
            'items.variante.producto:id,nombre,precio,categoria_id',
            'items.variante.unidad:id,nombre,abreviatura,fraccionable',
            'items.variante.color:id,nombre,hexadecimal',
            'pagos' => fn ($q) => $q->orderBy('id'),
            'pagos.usuario:id,name',
            'comprobantes' => fn ($q) => $q->latest('id'),
        ]);

        return (new PedidoResource($pedido))->resolve(request());
    }
}
