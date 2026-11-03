<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrdenCompraRequest;
use App\Http\Resources\OrdenCompraResource;
use App\Models\OrdenCompra;
use App\Services\OrdenesCompra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las órdenes de compra no se borran: se anulan o se reciben con una compra.
 * Sólo una pendiente (todavía no enviada al proveedor) se edita.
 */
class OrdenCompraController extends Controller
{
    public function __construct(private OrdenesCompra $ordenes) {}

    public function index(Request $request): JsonResponse
    {
        $query = OrdenCompra::query()
            ->with(['proveedor:id,ruc,razon_social', 'sede:id,nombre', 'compra:id,codigo,estado'])
            ->withCount('items');

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('ordenes_compra.codigo', 'like', $term)
                ->orWhereHas('proveedor', fn (Builder $p) => $p
                    ->where('razon_social', 'like', $term)
                    ->orWhere('ruc', 'like', $term)));
        }

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-id']);
        }

        return $this->generateViewSetList(
            $request,
            $query,
            ['estado', 'proveedor_id', 'sede_id'],
            [],
            ['id', 'total', 'fecha_entrega'],
            OrdenCompraResource::class,
        );
    }

    public function store(StoreOrdenCompraRequest $request): JsonResponse
    {
        $orden = $this->ordenes->guardar(new OrdenCompra, $request->validated('orden'), $request->user());

        return response()->json($this->conDetalle($orden), 201);
    }

    public function show(OrdenCompra $orden): JsonResponse
    {
        return response()->json($this->conDetalle($orden));
    }

    public function update(StoreOrdenCompraRequest $request, OrdenCompra $orden): JsonResponse
    {
        if (! $orden->editable()) {
            return response()->json(['message' => "Un pedido de compra {$orden->estadoTexto()} ya no se edita."], 409);
        }

        $this->ordenes->guardar($orden, $request->validated('orden'), $request->user());

        return response()->json($this->conDetalle($orden));
    }

    public function enviar(OrdenCompra $orden): JsonResponse
    {
        return response()->json($this->conDetalle($this->ordenes->enviar($orden)));
    }

    public function anular(Request $request, OrdenCompra $orden): JsonResponse
    {
        $motivo = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
        ], [], ['motivo' => 'motivo'])['motivo'];

        return response()->json($this->conDetalle($this->ordenes->anular($orden, $motivo)));
    }

    /**
     * @return array<string, mixed>
     */
    private function conDetalle(OrdenCompra $orden): array
    {
        $orden->load([
            'proveedor',
            'sede:id,nombre,direccion,telefono',
            'usuario:id,name',
            'compra:id,codigo,estado',
            'items' => fn ($q) => $q->orderBy('id'),
            'items.variante.producto:id,nombre,maneja_lotes',
            'items.variante.unidad:id,nombre,abreviatura,fraccionable',
            'items.variante.color:id,nombre,hexadecimal',
        ]);

        return (new OrdenCompraResource($orden))->resolve(request());
    }
}
