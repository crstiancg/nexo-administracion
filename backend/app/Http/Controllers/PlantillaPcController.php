<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantillaPcRequest;
use App\Http\Resources\VarianteStockResource;
use App\Models\PlantillaPc;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plantillas de PC armada para el form de cotizaciones: se cargan, se
 * guardan (mismo nombre = se reemplaza) y se borran.
 */
class PlantillaPcController extends Controller
{
    /**
     * Listas para cargar: cada ítem del catálogo con la variante de HOY (precio
     * y stock de la sede del usuario). Si ya no se vende en la sede o el
     * producto se desactivó, vuelve como casillero vacío para llenarlo.
     */
    public function index(Request $request): JsonResponse
    {
        $plantillas = PlantillaPc::query()->with('usuario:id,name')->orderBy('nombre')->get();

        $ids = $plantillas->flatMap(fn (PlantillaPc $p) => array_column($p->items, 'variante_id'))->filter()->unique()->values();
        $sedeId = $request->user()->sede_id;
        $variantes = Variante::query()
            ->whereIn('variantes.id', $ids)
            ->when($sedeId, fn (Builder $q) => $q->habilitadaEnSede($sedeId)->conStockDeSede($sedeId))
            ->whereHas('producto', fn (Builder $p) => $p->where('activo', true))
            ->with(['producto:id,nombre,precio,categoria_id', 'unidad:id,nombre,abreviatura,fraccionable', 'color:id,nombre,hexadecimal'])
            ->get()
            ->keyBy('id');

        return response()->json(['data' => $plantillas->map(fn (PlantillaPc $plantilla) => [
            'id' => $plantilla->id,
            'nombre' => $plantilla->nombre,
            'garantia' => $plantilla->garantia,
            'usuario' => $plantilla->usuario?->only(['id', 'name']),
            'actualizada' => $plantilla->updated_at?->toIso8601String(),
            'items' => array_map(function (array $item) use ($variantes, $request) {
                $variante = $item['variante_id'] ? $variantes->get($item['variante_id']) : null;

                return [
                    'componente' => $item['componente'],
                    'variante_id' => $variante?->id,
                    'variante' => $variante ? (new VarianteStockResource($variante))->resolve($request) : null,
                    'descripcion' => $item['descripcion'] ?? '',
                    'unidad' => $item['unidad'] ?? null,
                    'cantidad' => $item['cantidad'],
                    // Los del catálogo toman el precio de hoy (lo pone el form).
                    'precio_unitario' => $variante ? null : $item['precio_unitario'],
                    // Estaba en la plantilla pero ya no se puede vender en esta sede.
                    'no_disponible' => $item['variante_id'] && ! $variante,
                ];
            }, $plantilla->items),
        ])]);
    }

    public function store(StorePlantillaPcRequest $request): JsonResponse
    {
        $datos = $request->plantilla();
        $plantilla = PlantillaPc::query()->firstOrNew(['nombre' => $datos['nombre']]);
        $nueva = ! $plantilla->exists;
        $plantilla->fill($datos)->forceFill(['user_id' => $request->user()->id])->save();

        return response()->json(['id' => $plantilla->id, 'nombre' => $plantilla->nombre], $nueva ? 201 : 200);
    }

    public function destroy(PlantillaPc $plantilla): JsonResponse
    {
        $plantilla->delete();

        return response()->json(null, 204);
    }
}
