<?php

namespace App\Http\Controllers;

use App\Models\ConceptoFinanciero;
use App\Models\MovimientoFinanciero;
use App\Models\Pago;
use App\Services\Finanzas;
use App\Support\Fechas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Ingresos y gastos que no son ventas (alquiler, sueldos, luz…). No se
 * editan: un error se elimina y se vuelve a cargar (salvo lo que pasó por la
 * caja, que queda en su arqueo).
 */
class MovimientoFinancieroController extends Controller
{
    public function __construct(private Finanzas $finanzas) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = MovimientoFinanciero::query()
            ->with(['concepto:id,nombre,tipo', 'usuario:id,name', 'sede:id,nombre'])
            ->when($request->filled('desde'), fn (Builder $q) => $q->whereDate('fecha', '>=', $request->date('desde')))
            ->when($request->filled('hasta'), fn (Builder $q) => $q->whereDate('fecha', '<=', $request->date('hasta')))
            ->when($request->filled('search'), fn (Builder $q) => $q->where(fn (Builder $b) => $b
                ->whereLike('descripcion', '%'.$request->input('search').'%')
                ->orWhereHas('concepto', fn (Builder $c) => $c->whereLike('nombre', '%'.$request->input('search').'%'))));

        // Totales del filtro completo (no sólo de la página que se ve).
        $totales = (clone $query)
            ->when($request->filled('concepto_id'), fn (Builder $q) => $q->where('concepto_id', $request->integer('concepto_id')))
            ->when($request->filled('sede_id'), fn (Builder $q) => $q->where('sede_id', $request->integer('sede_id')))
            ->when($request->filled('metodo'), fn (Builder $q) => $q->where('metodo', $request->input('metodo')))
            ->selectRaw('tipo, SUM(monto) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-fecha,-id']);
        }

        $respuesta = $this->generateViewSetList(
            $request,
            $query,
            ['tipo', 'concepto_id', 'sede_id', 'metodo'],
            [],
            ['id', 'fecha', 'monto'],
        )->getData(true);

        $respuesta['totales'] = [
            'ingresos' => round((float) ($totales[ConceptoFinanciero::INGRESO] ?? 0), 2),
            'gastos' => round((float) ($totales[ConceptoFinanciero::GASTO] ?? 0), 2),
        ];

        return response()->json($respuesta);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'movimiento.concepto_id' => ['required', 'integer', Rule::exists('conceptos_financieros', 'id')->where('activo', true)],
            'movimiento.monto' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'movimiento.metodo' => ['required', Rule::in(array_keys(Pago::METODOS))],
            'movimiento.fecha' => ['required', 'date', 'before_or_equal:'.Fechas::hoyIso()],
            'movimiento.descripcion' => ['nullable', 'string', 'max:250'],
            'movimiento.referencia' => ['nullable', 'string', 'max:40'],
            'movimiento.de_caja' => ['boolean'],
        ], [
            'movimiento.fecha.before_or_equal' => 'La fecha no puede ser futura.',
        ], [
            'movimiento.concepto_id' => 'concepto',
            'movimiento.monto' => 'monto',
            'movimiento.metodo' => 'método',
            'movimiento.fecha' => 'fecha',
            'movimiento.descripcion' => 'descripción',
        ])['movimiento'];

        $movimiento = $this->finanzas->registrar($datos, $request->user());

        return response()->json($movimiento->load(['concepto:id,nombre,tipo', 'usuario:id,name']), 201);
    }

    public function destroy(MovimientoFinanciero $movimiento): Response
    {
        $this->finanzas->eliminar($movimiento);

        return response()->noContent();
    }
}
