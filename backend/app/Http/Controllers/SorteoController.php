<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSorteoRequest;
use App\Http\Requests\VenderTicketsRequest;
use App\Http\Resources\PedidoResource;
use App\Http\Resources\SorteoResource;
use App\Models\Sorteo;
use App\Models\SorteoPremio;
use App\Models\SorteoTicket;
use App\Services\Exportaciones;
use App\Services\Sorteos;
use App\Services\VentaTickets;
use App\Support\Fechas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sorteos de fidelización. Los tickets no se crean por acá: nacen de las
 * ventas (App\Services\Sorteos). Un sorteo con tickets no se borra: se
 * cancela (los clientes ya los tienen impresos).
 */
class SorteoController extends Controller
{
    public function __construct(private Sorteos $sorteos) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->conConteos(Sorteo::query()->with('sedes:id,nombre')->withCount('premios'));

        // El estado visible depende de hoy: se filtra por fechas.
        $hoy = Fechas::hoy()->toDateString();
        match ($request->input('estado')) {
            'programado' => $query->where('estado', Sorteo::ABIERTO)->whereDate('inicia_el', '>', $hoy),
            'abierto' => $query->where('estado', Sorteo::ABIERTO)->whereDate('inicia_el', '<=', $hoy)->whereDate('termina_el', '>=', $hoy),
            'por_sortear' => $query->where(fn (Builder $q) => $q
                ->where('estado', Sorteo::SORTEANDO)
                ->orWhere(fn (Builder $a) => $a->where('estado', Sorteo::ABIERTO)->whereDate('termina_el', '<', $hoy))),
            'sorteado', 'cancelado' => $query->where('estado', $request->input('estado')),
            default => null,
        };

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-inicia_el']);
        }

        return $this->generateViewSetList($request, $query, [], ['nombre'], ['id', 'nombre', 'inicia_el', 'termina_el'], SorteoResource::class);
    }

    public function store(StoreSorteoRequest $request): JsonResponse
    {
        $sorteo = DB::transaction(function () use ($request) {
            $datos = $request->validated('sorteo');
            $sorteo = new Sorteo($datos);
            $sorteo->forceFill(['estado' => Sorteo::ABIERTO, 'user_id' => $request->user()->id])->save();
            $this->sincronizar($sorteo, $datos);

            return $sorteo;
        });

        return response()->json($this->detalle($sorteo), 201);
    }

    public function show(Sorteo $sorteo): JsonResponse
    {
        return response()->json($this->detalle($sorteo));
    }

    public function update(StoreSorteoRequest $request, Sorteo $sorteo): JsonResponse
    {
        DB::transaction(function () use ($request, $sorteo) {
            $sorteo = Sorteo::query()->whereKey($sorteo->id)->lockForUpdate()->firstOrFail();
            $this->exigirAbierto($sorteo, 'editar');
            $datos = $request->validated('sorteo');
            $sorteo->update($datos);
            $this->sincronizar($sorteo, $datos);
        });

        return response()->json($this->detalle($sorteo));
    }

    public function destroy(Sorteo $sorteo): Response
    {
        if ($sorteo->tickets()->exists()) {
            throw ValidationException::withMessages([
                'sorteo' => 'Ya tiene tickets entregados a clientes: cancélalo en vez de borrarlo.',
            ])->status(409);
        }
        $sorteo->delete();

        return response()->noContent();
    }

    public function cancelar(Sorteo $sorteo): JsonResponse
    {
        DB::transaction(function () use ($sorteo) {
            $sorteo = Sorteo::query()->whereKey($sorteo->id)->lockForUpdate()->firstOrFail();
            if (in_array($sorteo->estado, [Sorteo::SORTEADO, Sorteo::CANCELADO], true)) {
                throw ValidationException::withMessages(['sorteo' => "El sorteo ya está {$sorteo->estado}."])->status(409);
            }
            $sorteo->forceFill(['estado' => Sorteo::CANCELADO])->save();
        });

        return response()->json($this->detalle($sorteo));
    }

    /** Los tickets: por número, cliente o documento; `anulados=1` los incluye. */
    public function tickets(Request $request, Sorteo $sorteo): JsonResponse
    {
        $query = $sorteo->tickets()->getQuery()
            ->with(['cliente:id,nombre,tipo_documento,numero_documento,telefono', 'pedido:id,codigo,total', 'sede:id,nombre'])
            ->when(! $request->boolean('anulados'), fn (Builder $q) => $q->vigentes())
            ->when($request->filled('cliente_id'), fn (Builder $q) => $q->where('cliente_id', $request->integer('cliente_id')));

        if ($request->filled('search')) {
            $termino = trim((string) $request->input('search'));
            $numero = (int) ltrim($termino, '#0');
            $query->where(fn (Builder $q) => $q
                ->when($numero > 0, fn ($n) => $n->where('numero', $numero))
                ->orWhereHas('cliente', fn (Builder $c) => $c
                    ->whereLike('nombre', "%{$termino}%")
                    ->orWhereLike('numero_documento', "%{$termino}%")));
        }

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-numero']);
        }

        $pagina = $this->generateViewSetList($request, $query, [], [], ['numero', 'created_at'])->getData(true);
        $pagina['data'] = array_map(fn ($t) => $this->ticket($t), $pagina['data']);

        return response()->json($pagina);
    }

    /** Cada cliente con cuántos tickets vigentes tiene (más chances arriba). */
    public function participantes(Request $request, Sorteo $sorteo): JsonResponse
    {
        $query = $this->participantesQuery($sorteo);

        if ($request->filled('search')) {
            $termino = '%'.trim((string) $request->input('search')).'%';
            $query->where(fn ($q) => $q->whereLike('clientes.nombre', $termino)->orWhereLike('clientes.numero_documento', $termino));
        }

        $tamano = $this->getPageSize($request);
        // Con groupBy, count() daría los tickets del primer cliente.
        $total = (clone $query)->getCountForPagination();
        $filas = $tamano ? $query->forPage(max(1, $request->integer('page', 1)), $tamano)->get() : $query->get();
        $enJuego = max(1, (int) $sorteo->tickets()->vigentes()->count());
        // Los números de cada uno (para avisarle cuáles tiene). Aparte y no con
        // GROUP_CONCAT / string_agg: sirve igual en MySQL y en Postgres.
        $numeros = $sorteo->tickets()->vigentes()
            ->whereIn('cliente_id', $filas->pluck('cliente_id'))
            ->orderBy('numero')
            ->get(['cliente_id', 'numero'])
            ->groupBy('cliente_id')
            ->map(fn ($tickets) => $tickets->map(fn ($t) => $t->codigo())->values());

        return response()->json([
            'data' => $filas->map(fn ($f) => [
                'cliente_id' => $f->cliente_id,
                'nombre' => $f->nombre,
                'tipo_documento' => $f->tipo_documento,
                'numero_documento' => $f->numero_documento,
                'telefono' => $f->telefono,
                'tickets' => (int) $f->tickets,
                'numeros' => $numeros->get($f->cliente_id, collect())->all(),
                // Su chance en el próximo premio, si todos siguen en juego.
                'probabilidad' => round((int) $f->tickets / $enJuego * 100, 2),
            ])->values(),
            'total' => $total,
        ]);
    }

    /**
     * Vende tickets sueltos (precio y cantidad a criterio). Devuelve la venta
     * lista para imprimir su ticket (con los números).
     */
    public function vender(VenderTicketsRequest $request, Sorteo $sorteo, VentaTickets $ventas): JsonResponse
    {
        $pedido = $ventas->vender($sorteo, $request->validated('venta'), $request->user());

        $pedido->load([
            'cliente',
            'sede:id,nombre,direccion,telefono',
            'usuario:id,name',
            'items' => fn ($q) => $q->orderBy('id'),
            'pagos' => fn ($q) => $q->orderBy('id'),
            'pagos.usuario:id,name',
            'sorteoTickets.sorteo:id,nombre,termina_el',
        ]);

        return response()->json((new PedidoResource($pedido))->resolve($request), 201);
    }

    public function excel(Sorteo $sorteo, Exportaciones $exportar): StreamedResponse
    {
        $sorteo->load(['sedes:id,nombre', 'premios.ticket.cliente']);
        $tickets = $sorteo->tickets()->with(['cliente', 'pedido:id,codigo,total', 'sede:id,nombre'])->orderBy('numero')->get();
        $participantes = $this->participantesQuery($sorteo)->get();

        $nombre = preg_replace('/[^A-Za-z0-9\-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $sorteo->nombre) ?: 'sorteo');

        return $exportar->descargar($exportar->sorteo($sorteo, $participantes, $tickets), trim($nombre, '-').'.xlsx');
    }

    /**
     * Para la ruleta: hasta 150 tickets en juego al azar (sólo para animar:
     * el ganador lo elige el servidor al sortear).
     */
    public function candidatos(Sorteo $sorteo): JsonResponse
    {
        $query = $this->sorteos->candidatos($sorteo);
        $total = (clone $query)->count();
        $muestra = $query->with('cliente:id,nombre,tipo_documento,numero_documento')->inRandomOrder()->limit(150)->get();

        return response()->json([
            'total' => $total,
            'data' => $muestra->map(fn (SorteoTicket $t) => SorteoResource::publico($t))->values(),
        ]);
    }

    public function sortear(Request $request, Sorteo $sorteo, SorteoPremio $premio): JsonResponse
    {
        $premio = $this->sorteos->sortear($sorteo, $premio, $request->user());

        return response()->json([
            'premio' => SorteoResource::premio($premio),
            'sorteo' => $this->detalle($sorteo),
        ]);
    }

    public function descartar(Request $request, Sorteo $sorteo, SorteoPremio $premio): JsonResponse
    {
        $motivo = $request->validate(['motivo' => ['required', 'string', 'max:200']])['motivo'];
        $premio = $this->sorteos->descartar($sorteo, $premio, $motivo, $request->user());

        return response()->json([
            'premio' => SorteoResource::premio($premio),
            'sorteo' => $this->detalle($sorteo),
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function sincronizar(Sorteo $sorteo, array $datos): void
    {
        $sorteo->sedes()->sync($datos['sedes'] ?? []);

        // Abierto: todavía nadie ganó nada, los premios se reescriben.
        $sorteo->premios()->delete();
        foreach (array_values($datos['premios']) as $i => $premio) {
            $sorteo->premios()->create(['orden' => $i + 1, 'descripcion' => $premio['descripcion']]);
        }
    }

    private function exigirAbierto(Sorteo $sorteo, string $accion): void
    {
        if ($sorteo->estado !== Sorteo::ABIERTO) {
            throw ValidationException::withMessages([
                'sorteo' => "No se puede {$accion} un sorteo {$sorteo->estado}.",
            ])->status(409);
        }
    }

    private function conConteos(Builder $query): Builder
    {
        return $query
            ->withCount(['tickets as tickets_vigentes_count' => fn (Builder $q) => $q->whereNull('anulado_at')])
            ->addSelect(['participantes_count' => SorteoTicket::query()
                ->selectRaw('COUNT(DISTINCT cliente_id)')
                ->whereColumn('sorteo_id', 'sorteos.id')
                ->whereNull('anulado_at')]);
    }

    private function participantesQuery(Sorteo $sorteo)
    {
        return DB::table('sorteo_tickets')
            ->join('clientes', 'clientes.id', '=', 'sorteo_tickets.cliente_id')
            ->where('sorteo_tickets.sorteo_id', $sorteo->id)
            ->whereNull('sorteo_tickets.anulado_at')
            ->groupBy('sorteo_tickets.cliente_id', 'clientes.nombre', 'clientes.tipo_documento', 'clientes.numero_documento', 'clientes.telefono')
            ->select([
                'sorteo_tickets.cliente_id',
                'clientes.nombre',
                'clientes.tipo_documento',
                'clientes.numero_documento',
                'clientes.telefono',
                DB::raw('COUNT(*) as tickets'),
            ])
            ->orderByDesc('tickets')
            ->orderBy('clientes.nombre');
    }

    /**
     * @param  array<string, mixed>  $t  ticket ya serializado
     * @return array<string, mixed>
     */
    private function ticket(array $t): array
    {
        return [
            'id' => $t['id'],
            'numero' => $t['numero'],
            'codigo' => '#'.str_pad((string) $t['numero'], 6, '0', STR_PAD_LEFT),
            'cliente' => $t['cliente'] ?? null,
            'pedido' => $t['pedido'] ?? null,
            'sede' => $t['sede']['nombre'] ?? null,
            'anulado' => $t['anulado_at'] !== null,
            // Comprado (venta de tickets) o de regalo por una compra / OS.
            'vendido' => (bool) ($t['vendido'] ?? false),
            'fecha' => $t['created_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detalle(Sorteo $sorteo): array
    {
        $sorteo = $this->conConteos(Sorteo::query()->whereKey($sorteo->id))
            ->with(['sedes:id,nombre', 'usuario:id,name', 'premios.ticket.cliente', 'premios.sorteadoPor:id,name'])
            ->firstOrFail();

        return (new SorteoResource($sorteo))->resolve(request());
    }
}
