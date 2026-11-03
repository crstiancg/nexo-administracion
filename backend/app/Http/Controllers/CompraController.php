<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompraRequest;
use App\Http\Resources\CompraResource;
use App\Models\Compra;
use App\Services\Compras;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las compras no se editan ni se borran: se anulan (la anulación saca la
 * mercadería del inventario). Corregir una compra = anularla y cargarla bien.
 */
class CompraController extends Controller
{
    public function __construct(private Compras $compras) {}

    public function index(Request $request): JsonResponse
    {
        $query = Compra::query()
            ->with(['proveedor:id,ruc,razon_social', 'sede:id,nombre'])
            ->withCount('items');

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('compras.codigo', 'like', $term)
                ->orWhere('compras.numero_documento', 'like', $term)
                ->orWhereHas('proveedor', fn (Builder $p) => $p
                    ->where('razon_social', 'like', $term)
                    ->orWhere('ruc', 'like', $term)));
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->date('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->date('hasta'));
        }

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-id']);
        }

        return $this->generateViewSetList(
            $request,
            $query,
            ['estado', 'proveedor_id', 'sede_id'],
            [],
            ['id', 'fecha', 'total'],
            CompraResource::class,
        );
    }

    public function store(StoreCompraRequest $request): JsonResponse
    {
        $compra = $this->compras->registrar($request->validated('compra'), $request->user());

        return response()->json($this->conDetalle($compra), 201);
    }

    public function show(Compra $compra): JsonResponse
    {
        return response()->json($this->conDetalle($compra));
    }

    public function anular(Request $request, Compra $compra): JsonResponse
    {
        $motivo = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
        ], [], ['motivo' => 'motivo'])['motivo'];

        return response()->json($this->conDetalle($this->compras->anular($compra, $motivo, $request->user())));
    }

    /**
     * @return array<string, mixed>
     */
    private function conDetalle(Compra $compra): array
    {
        $compra->load([
            'proveedor',
            'sede:id,nombre',
            'usuario:id,name',
            'anuladaPor:id,name',
            'items' => fn ($q) => $q->orderBy('id'),
            'items.variante.producto:id,nombre',
            'items.variante.unidad:id,nombre,abreviatura,fraccionable',
            'items.variante.color:id,nombre,hexadecimal',
        ]);

        return (new CompraResource($compra))->resolve(request());
    }
}
