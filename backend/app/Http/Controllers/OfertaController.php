<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOfertaRequest;
use App\Http\Resources\OfertaResource;
use App\Models\Oferta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Las ofertas se pueden borrar sin miedo: no cambian nada guardado (el
 * precio de cada venta quedó congelado en su pedido).
 */
class OfertaController extends Controller
{
    private const RELACIONES = [
        'productos:id,nombre,precio',
        'variantes:id,producto_id,sku,presentacion,color_id',
        'variantes.producto:id,nombre,precio',
        'variantes.color:id,nombre,hexadecimal',
        'categorias:id,nombre',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Oferta::query()->with(self::RELACIONES);

        // El estado depende de "ahora": se filtra por fechas, no por columna.
        $ahora = now();
        match ($request->input('estado')) {
            'vigente' => $query->vigentes($ahora),
            'programada' => $query->where('activa', true)->where('inicia_at', '>', $ahora),
            'vencida' => $query->where('termina_at', '<=', $ahora),
            'pausada' => $query->where('activa', false)->where('termina_at', '>', $ahora),
            default => null,
        };

        // Por nombre de la oferta o de cualquiera de sus productos/categorías.
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('ofertas.nombre', 'like', $term)
                ->orWhereHas('productos', fn (Builder $p) => $p->where('nombre', 'like', $term))
                ->orWhereHas('variantes.producto', fn (Builder $p) => $p->where('nombre', 'like', $term))
                ->orWhereHas('categorias', fn (Builder $c) => $c->where('nombre', 'like', $term)));
        }

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-inicia_at']);
        }

        return $this->generateViewSetList(
            $request,
            $query,
            [],
            [],
            ['id', 'nombre', 'inicia_at', 'termina_at'],
            OfertaResource::class,
        );
    }

    public function store(StoreOfertaRequest $request): JsonResponse
    {
        $oferta = DB::transaction(function () use ($request) {
            $oferta = Oferta::create($this->datos($request));
            $this->sincronizarDestinos($oferta, $request->validated('oferta'));

            return $oferta;
        });

        return response()->json($this->detalle($oferta), 201);
    }

    public function show(Oferta $oferta): JsonResponse
    {
        return response()->json($this->detalle($oferta));
    }

    public function update(StoreOfertaRequest $request, Oferta $oferta): JsonResponse
    {
        DB::transaction(function () use ($request, $oferta) {
            $oferta->update($this->datos($request));
            $this->sincronizarDestinos($oferta, $request->validated('oferta'));
        });

        return response()->json($this->detalle($oferta));
    }

    public function destroy(Oferta $oferta): Response
    {
        $oferta->delete();

        return response()->noContent();
    }

    /**
     * Un producto sin variantes elegidas entra completo (también sus
     * variantes futuras); con variantes elegidas, entran sólo ésas.
     *
     * @param  array<string, mixed>  $datos
     */
    private function sincronizarDestinos(Oferta $oferta, array $datos): void
    {
        $productos = [];
        $variantes = [];
        foreach ($datos['productos'] ?? [] as $item) {
            if (empty($item['variantes'])) {
                $productos[] = $item['producto_id'];
            } else {
                $variantes = [...$variantes, ...$item['variantes']];
            }
        }

        $oferta->productos()->sync($productos);
        $oferta->variantes()->sync($variantes);
        $oferta->categorias()->sync($datos['categorias'] ?? []);
    }

    /**
     * Los campos propios de la oferta (los destinos van por su tabla).
     *
     * @return array<string, mixed>
     */
    private function datos(StoreOfertaRequest $request): array
    {
        $datos = collect($request->validated('oferta'))
            ->except(['alcance', 'productos', 'categorias'])
            ->all();

        // Eloquent DESCARTA el offset de un string ISO al guardar un datetime
        // ("23:59-05:00" quedaría 23:59 UTC: 5 horas antes). Se convierte a
        // la zona de la app (UTC) antes de asignar.
        foreach (['inicia_at', 'termina_at'] as $campo) {
            $datos[$campo] = Carbon::parse($datos[$campo])->setTimezone(config('app.timezone'));
        }

        return $datos;
    }

    /**
     * @return array<string, mixed>
     */
    private function detalle(Oferta $oferta): array
    {
        // refresh: un modelo recién creado no tiene los defaults de la base
        // (activa), y el estado saldría "pausada".
        return (new OfertaResource($oferta->refresh()->load(self::RELACIONES)))->resolve(request());
    }
}
