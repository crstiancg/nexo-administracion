<?php

namespace App\Http\Controllers;

use App\Http\Resources\EtiquetaResource;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Variantes para la pantalla de impresión de etiquetas. La etiqueta se arma
 * e imprime en el navegador; acá sólo se buscan las variantes.
 */
class EtiquetaController extends Controller
{
    /**
     * Por producto (todas sus variantes) o por búsqueda (nombre del
     * producto, SKU o código de barras).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Variante::query()
            ->with(['producto:id,nombre', 'unidad:id,abreviatura', 'color:id,nombre,hexadecimal']);

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('variantes.sku', 'like', $term)
                ->orWhere('variantes.codigo_barras', 'like', $term)
                ->orWhere('productos.nombre', 'like', $term));
        }

        // Por nombre de producto y, dentro, en el orden en que se cargaron.
        $query->join('productos', 'productos.id', '=', 'variantes.producto_id')
            ->select('variantes.*')
            ->when($request->user()->sede_id, fn ($q, $sedeId) => $q->habilitadaEnSede($sedeId)->conStockDeSede($sedeId))
            ->orderBy('productos.nombre')
            ->orderBy('variantes.producto_id')
            ->orderBy('variantes.id');

        return $this->generateViewSetList($request, $query, ['producto_id'], [], [], EtiquetaResource::class);
    }
}
