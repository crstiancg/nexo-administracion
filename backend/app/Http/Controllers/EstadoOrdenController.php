<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstadoOrdenRequest;
use App\Models\EstadoOrden;
use App\Models\OrdenServicio;
use App\Models\OrdenServicioMovimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catálogos > Estados de orden. Pocos y ordenados a mano: la lista va
 * entera, sin paginar, en el orden del flujo del taller.
 */
class EstadoOrdenController extends Controller
{
    public function index(): JsonResponse
    {
        $ordenes = OrdenServicio::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return response()->json(EstadoOrden::query()
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn (EstadoOrden $e) => [
                ...$e->toArray(),
                'de_sistema' => $e->esDeSistema(),
                'ordenes_count' => (int) ($ordenes[$e->clave] ?? 0),
            ]));
    }

    public function store(StoreEstadoOrdenRequest $request): JsonResponse
    {
        $datos = $request->validated('estado');

        $estado = DB::transaction(function () use ($datos) {
            $estado = (new EstadoOrden($datos))->forceFill(['clave' => $this->claveNueva($datos['nombre'])]);

            // Entra después del último de su tipo (o de los que van antes en el
            // flujo): un "en proceso" nuevo queda antes de Reparado, no detrás
            // de Entregado. Después se mueve con las flechas.
            $lista = EstadoOrden::query()->orderBy('orden')->orderBy('id')->get()->values();
            $rango = array_flip(array_keys(EstadoOrden::TIPOS));
            $despuesDe = $lista->filter(fn (EstadoOrden $e) => ($rango[$e->tipo] ?? 0) <= $rango[$estado->tipo])->keys()->last();
            $lista->splice($despuesDe === null ? 0 : $despuesDe + 1, 0, [$estado]);

            $lista->each(function (EstadoOrden $e, int $i) {
                $e->forceFill(['orden' => $i + 1]);
                if ($e->isDirty()) {
                    $e->save();
                }
            });

            return $estado;
        });

        return response()->json($estado, 201);
    }

    public function show(EstadoOrden $estado): JsonResponse
    {
        return response()->json([...$estado->toArray(), 'de_sistema' => $estado->esDeSistema()]);
    }

    public function update(StoreEstadoOrdenRequest $request, EstadoOrden $estado): JsonResponse
    {
        // La clave no cambia: la tienen las órdenes y su historial.
        $estado->update($request->validated('estado'));

        return response()->json($estado);
    }

    /** Sólo uno que nunca se usó; si no, se desactiva. */
    public function destroy(EstadoOrden $estado): JsonResponse|Response
    {
        if ($estado->esDeSistema()) {
            return response()->json(['message' => 'Este estado es del sistema: no se puede eliminar.'], 409);
        }

        $usado = OrdenServicio::query()->where('estado', $estado->clave)->exists()
            || OrdenServicioMovimiento::query()->where('estado', $estado->clave)->exists();
        if ($usado) {
            return response()->json([
                'message' => 'No se puede eliminar: hay órdenes que pasaron por este estado. Desactívelo en su lugar.',
            ], 409);
        }

        $estado->delete();

        return response()->noContent();
    }

    /** Nuevo orden de la lista: `ids` de arriba abajo (todos los estados). */
    public function ordenar(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:estados_orden,id'],
        ])['ids'];

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                EstadoOrden::query()->whereKey($id)->update(['orden' => $i + 1]);
            }
        });
        EstadoOrden::olvidar();

        return $this->index();
    }

    /** "Enviado a laboratorio" → enviado_a_laboratorio (sin repetir). */
    private function claveNueva(string $nombre): string
    {
        $base = Str::limit(Str::slug($nombre, '_'), 16, '') ?: 'estado';
        $clave = $base;
        for ($i = 2; EstadoOrden::query()->where('clave', $clave)->exists(); $i++) {
            $clave = "{$base}_{$i}";
        }

        return $clave;
    }
}
