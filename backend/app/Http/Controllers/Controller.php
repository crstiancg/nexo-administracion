<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class Controller
{
    /**
     * Tamaño de página pedido por el front. Acepta los tres nombres que usa el
     * patrón de sistema-botica; 0 significa "sin paginar, traer todo".
     */
    protected function getPageSize(Request $request): int
    {
        foreach (['rowsPerPage', 'per_page', 'page_size'] as $key) {
            if ($request->filled($key)) {
                return max(0, (int) $request->input($key));
            }
        }

        return 20;
    }

    /**
     * Listado estándar para las tablas del front (patrón de sistema-botica):
     * filtros exactos, búsqueda `search` con LIKE, orden `order_by` (`-col` =
     * desc) y paginación. Responde el paginador de Laravel (`data`, `total`,
     * `current_page`...) o `{ data }` sin paginar cuando rowsPerPage=0.
     *
     * Sólo se ordena por columnas de la lista blanca: `order_by` viene del
     * cliente y va directo al SQL.
     *
     * Con `$resource` (una clase JsonResource) cada fila sale por ese contrato
     * en vez del toArray() del modelo, sin cambiar la forma del paginador.
     *
     * @param  string[]  $filterBy
     * @param  string[]  $searchBy
     * @param  string[]  $orderBy
     * @param  class-string<JsonResource>|null  $resource
     */
    protected function generateViewSetList(
        Request $request,
        Builder $query,
        array $filterBy,
        array $searchBy,
        array $orderBy,
        ?string $resource = null,
    ): JsonResponse {
        $table = $query->getModel()->getTable();
        $qualify = fn (string $column) => str_contains($column, '.') ? $column : "{$table}.{$column}";

        foreach ($filterBy as $filter) {
            if ($request->filled($filter)) {
                $query->where($qualify($filter), $request->input($filter));
            }
        }

        if ($request->filled('search') && $searchBy !== []) {
            $term = '%'.$request->input('search').'%';
            $query->where(function (Builder $q) use ($searchBy, $term, $qualify) {
                foreach ($searchBy as $column) {
                    $q->orWhere($qualify($column), 'like', $term);
                }
            });
        }

        if ($request->filled('order_by')) {
            foreach (explode(',', $request->input('order_by')) as $param) {
                $column = ltrim($param, '-');
                if (in_array($column, $orderBy, true)) {
                    $query->orderBy($qualify($column), str_starts_with($param, '-') ? 'desc' : 'asc');
                }
            }
        }

        $pageSize = $this->getPageSize($request);
        $salida = fn ($modelo) => $resource ? (new $resource($modelo))->resolve($request) : $modelo;

        return $pageSize > 0
            ? response()->json($query->paginate($pageSize)->through($salida))
            : response()->json(['data' => $query->get()->map($salida)]);
    }
}
