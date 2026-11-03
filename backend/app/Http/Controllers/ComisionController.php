<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\User;
use App\Services\Comisiones;
use App\Support\Fechas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Comisiones de técnicos y vendedores por período (por defecto, el mes en
 * curso): los porcentajes de cada uno, el cálculo y el pago.
 */
class ComisionController extends Controller
{
    public function __construct(private Comisiones $comisiones) {}

    public function index(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request);

        return response()->json([
            'desde' => $desde,
            'hasta' => $hasta,
            'data' => $this->comisiones->calcular($desde, $hasta, $request->integer('sede_id') ?: null, $request->user()),
        ]);
    }

    /** Los porcentajes de cada empleado: [{ id, reparacion, venta }]. */
    public function porcentajes(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'empleados' => ['required', 'array', 'min:1'],
            'empleados.*.id' => ['required', 'integer', 'exists:users,id'],
            'empleados.*.reparacion' => ['required', 'numeric', 'min:0', 'max:100'],
            'empleados.*.venta' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [], ['empleados.*.reparacion' => '% de reparación', 'empleados.*.venta' => '% de venta']);

        $visibles = User::query()->visiblesPara($request->user())->pluck('id');
        foreach ($datos['empleados'] as $e) {
            if ($visibles->contains($e['id'])) {
                User::query()->whereKey($e['id'])->update([
                    'comision_reparacion' => round((float) $e['reparacion'], 2),
                    'comision_venta' => round((float) $e['venta'], 2),
                ]);
            }
        }

        return $this->index($request);
    }

    public function pagar(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request);
        $datos = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'pago.metodo' => ['required', Rule::in(array_keys(Pago::METODOS))],
            'pago.de_caja' => ['boolean'],
            'pago.referencia' => ['nullable', 'string', 'max:40'],
        ], [], ['pago.metodo' => 'método']);

        $empleado = User::query()->visiblesPara($request->user())->findOrFail($datos['user_id']);
        $this->comisiones->pagar($empleado, $desde, $hasta, $datos['pago'], $request->user());

        return $this->index($request);
    }

    /** @return array{0: string, 1: string} */
    private function periodo(Request $request): array
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        return [
            $request->filled('desde') ? $request->date('desde')->toDateString() : Fechas::hoy()->startOfMonth()->toDateString(),
            $request->filled('hasta') ? $request->date('hasta')->toDateString() : Fechas::hoyIso(),
        ];
    }
}
