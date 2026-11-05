<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use App\Support\Fechas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Quién cambió qué y cuándo (sólo lectura). Filtros: usuario, módulo
 * (`modelo`), evento, un registro puntual (`modelo` + `modelo_id`), rango de
 * días y búsqueda por nombre del registro.
 */
class AuditoriaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'modelo' => ['nullable', Rule::in(array_keys(Auditoria::MODELOS))],
            'evento' => ['nullable', 'in:creado,editado,borrado'],
            'user_id' => ['nullable', 'integer'],
            'modelo_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $pagina = Auditoria::query()
            ->with('usuario:id,name')
            ->when($request->filled('modelo'), fn ($q) => $q->where('modelo', $request->input('modelo')))
            ->when($request->filled('modelo_id'), fn ($q) => $q->where('modelo_id', $request->integer('modelo_id')))
            ->when($request->filled('evento'), fn ($q) => $q->where('evento', $request->input('evento')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('etiqueta', 'like', '%'.$request->input('search').'%'))
            ->when($request->filled('desde'), fn ($q) => $q->where('created_at', '>=', Fechas::inicioDelDia($request->date('desde'))))
            ->when($request->filled('hasta'), fn ($q) => $q->where('created_at', '<=', Fechas::finDelDia($request->date('hasta'))))
            ->latest('id')
            ->paginate(max(1, min(100, $request->integer('rowsPerPage', 30))));

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Auditoria $a) => [
                'id' => $a->id,
                'fecha' => $a->created_at?->toIso8601String(),
                'usuario' => $a->usuario?->only(['id', 'name']),
                'evento' => $a->evento,
                'modelo' => $a->modelo,
                'modelo_label' => Auditoria::MODELOS[$a->modelo] ?? $a->modelo,
                'modelo_id' => $a->modelo_id,
                'etiqueta' => $a->etiqueta,
                'cambios' => $a->cambios,
                'ip' => $a->ip,
            ]),
            'total' => $pagina->total(),
            'current_page' => $pagina->currentPage(),
            'per_page' => $pagina->perPage(),
            // Para los filtros.
            'modelos' => collect(Auditoria::MODELOS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'usuarios' => User::query()->whereIn('id', Auditoria::query()->whereNotNull('user_id')->distinct()->select('user_id'))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
