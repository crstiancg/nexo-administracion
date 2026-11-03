<?php

namespace App\Http\Controllers;

use App\Models\ConceptoFinanciero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ConceptoFinancieroController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->generateViewSetList(
            $request,
            ConceptoFinanciero::query()->withCount('movimientos'),
            ['tipo', 'activo'],
            ['nombre'],
            ['id', 'nombre', 'tipo'],
        );
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(ConceptoFinanciero::create($this->validar($request)), 201);
    }

    public function update(Request $request, ConceptoFinanciero $concepto): JsonResponse
    {
        $concepto->update($this->validar($request, $concepto));

        return response()->json($concepto);
    }

    public function destroy(ConceptoFinanciero $concepto): JsonResponse|Response
    {
        if ($concepto->movimientos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene movimientos registrados. Desactívalo en su lugar.',
            ], 409);
        }

        $concepto->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?ConceptoFinanciero $concepto = null): array
    {
        $request->merge(['concepto' => array_map(
            fn ($v) => is_string($v) ? trim($v) : $v,
            (array) $request->input('concepto', []),
        )]);

        return $request->validate([
            'concepto.tipo' => ['required', Rule::in(array_keys(ConceptoFinanciero::TIPOS))],
            'concepto.nombre' => [
                'required', 'string', 'max:80',
                Rule::unique('conceptos_financieros', 'nombre')
                    ->where('tipo', $request->input('concepto.tipo'))
                    ->ignore($concepto?->id),
            ],
            'concepto.activo' => ['boolean'],
        ], [
            'concepto.nombre.unique' => 'Ya existe un concepto con ese nombre.',
        ], ['concepto.tipo' => 'tipo', 'concepto.nombre' => 'nombre'])['concepto'];
    }
}
