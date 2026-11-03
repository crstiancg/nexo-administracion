<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignarLoteRequest;
use App\Http\Requests\CorregirEntradaRequest;
use App\Http\Requests\RegistrarAjusteRequest;
use App\Http\Requests\RegistrarEntradaRequest;
use App\Http\Requests\RegistrarSalidaRequest;
use App\Http\Requests\RegistrarTrasladoRequest;
use App\Http\Resources\LoteResource;
use App\Http\Resources\MovimientoResource;
use App\Http\Resources\VarianteStockResource;
use App\Models\Lote;
use App\Models\MovimientoInventario;
use App\Models\Variante;
use App\Services\Inventario;
use App\Support\Ean13;
use App\Support\Fechas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Libro de inventario: se consulta y se le agregan movimientos. No hay
 * destroy y la cantidad no se edita: un movimiento mal cargado se corrige
 * con otro. De una entrada sí se completan costo, referencia y lotes.
 */
class InventarioController extends Controller
{
    private const RELACIONES = ['lotes', 'variante.producto:id,nombre,maneja_lotes', 'variante.unidad:id,nombre,abreviatura,fraccionable', 'variante.color:id,nombre,hexadecimal'];

    public function __construct(private Inventario $inventario) {}

    /**
     * Historial, del más nuevo al más viejo.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MovimientoInventario::query()->with([...self::RELACIONES, 'usuario:id,name', 'sede:id,nombre', 'sedeRelacionada:id,nombre']);

        if ($request->filled('producto_id')) {
            $query->whereHas('variante', fn (Builder $q) => $q->where('producto_id', $request->integer('producto_id')));
        }
        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->date('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->date('hasta'));
        }

        // Por SKU, producto o referencia (n° de factura).
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('movimiento_inventarios.referencia', 'like', $term)
                ->orWhereHas('variante', fn (Builder $v) => $v
                    ->where('sku', 'like', $term)
                    ->orWhereHas('producto', fn (Builder $p) => $p->where('nombre', 'like', $term))));
        }

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-id']);
        }

        return $this->generateViewSetList(
            $request,
            $query,
            ['tipo', 'variante_id', 'grupo', 'sede_id'],
            [],
            ['id', 'tipo', 'cantidad'],
            MovimientoResource::class,
        );
    }

    /**
     * Buscador de presentaciones para las líneas: por SKU o nombre del
     * producto, con el stock de una sede (`sede_id`, por defecto la del
     * usuario) y el total de la empresa.
     */
    public function variantes(Request $request): JsonResponse
    {
        $sedeId = $request->integer('sede_id') ?: $request->user()->sede_id;

        // Con sede, sólo las presentaciones que esa sede vende.
        $query = Variante::query()
            ->when($sedeId, fn (Builder $q) => $q->habilitadaEnSede($sedeId)->conStockDeSede($sedeId))
            ->with(['producto:id,nombre,precio,categoria_id,marca_id,maneja_lotes', 'producto.portada', 'producto.marca:id,nombre', 'portada', 'unidad:id,nombre,abreviatura,fraccionable', 'color:id,nombre,hexadecimal'])
            ->whereHas('producto', fn (Builder $p) => $p->where('activo', true))
            // Por reponer de una marca (lo que se le pide a Sika, …).
            ->when($request->filled('marca_id'), fn (Builder $q) => $q->whereHas('producto', fn (Builder $p) => $p->where('marca_id', $request->integer('marca_id'))));

        // Lector de código de barras: el código de la etiqueta (EAN-13) o el
        // SKU exacto (se guardan en mayúsculas), así siguen sirviendo los dos.
        if ($request->filled('sku')) {
            $codigo = mb_strtoupper(trim($request->input('sku')));
            $query->where(fn (Builder $q) => $q
                ->whereIn('variantes.codigo_barras', Ean13::variantesDeLectura($codigo))
                ->orWhere('variantes.sku', $codigo));
        }

        // Varias por id (`ids=1,2,3`): el punto de venta refresca los precios
        // de lo que ya tiene en el carrito.
        if ($request->filled('ids')) {
            $query->whereIn('variantes.id', array_filter(array_map('intval', explode(',', (string) $request->input('ids')))));
        }

        // "Por reponer": en la sede, por debajo del mínimo.
        if ($request->boolean('bajo_minimo') && $sedeId) {
            $query->whereHas('stocks', fn (Builder $s) => $s
                ->where('sede_id', $sedeId)
                ->where('activo', true)
                ->where('stock_minimo', '>', 0)
                ->whereColumn('cantidad', '<', 'stock_minimo'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('variantes.sku', 'like', $term)
                ->orWhere('variantes.codigo_barras', 'like', $term)
                ->orWhereHas('producto', fn (Builder $p) => $p->where('nombre', 'like', $term)));
        }

        return $this->generateViewSetList(
            $request,
            $query->orderBy('producto_id')->orderBy('id'),
            ['producto_id'],
            [],
            [],
            VarianteStockResource::class,
        );
    }

    /**
     * Lotes con stock, el que vence primero arriba. Filtros: `sede_id` (por
     * defecto la del usuario; 0 = todas), `variante_id`, `producto_id`, `estado`
     * (vencido | por_vencer) y búsqueda por producto, SKU o código de lote.
     */
    public function lotes(Request $request): JsonResponse
    {
        $sedeId = $request->has('sede_id') ? $request->integer('sede_id') : $request->user()->sede_id;
        $hoy = Fechas::hoyIso();

        $query = Lote::query()
            ->with(['sede:id,nombre', 'variante.producto:id,nombre', 'variante.unidad:id,abreviatura', 'variante.color:id,nombre,hexadecimal'])
            ->where('cantidad', '>', 0)
            ->when($sedeId, fn (Builder $q) => $q->where('sede_id', $sedeId))
            ->when($request->filled('variante_id'), fn (Builder $q) => $q->where('variante_id', $request->integer('variante_id')))
            ->when($request->filled('producto_id'), fn (Builder $q) => $q->whereHas('variante', fn (Builder $v) => $v->where('producto_id', $request->integer('producto_id'))))
            ->when($request->input('estado') === 'vencido', fn (Builder $q) => $q->whereDate('vence_at', '<', $hoy))
            ->when($request->input('estado') === 'por_vencer', fn (Builder $q) => $q
                ->whereDate('vence_at', '>=', $hoy)
                ->whereDate('vence_at', '<=', Fechas::hoy()->addDays(Lote::DIAS_POR_VENCER)->toDateString()));

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('lotes.codigo', 'like', $term)
                ->orWhereHas('variante', fn (Builder $v) => $v
                    ->where('sku', 'like', $term)
                    ->orWhereHas('producto', fn (Builder $p) => $p->where('nombre', 'like', $term))));
        }

        // El que vence primero arriba; los sin fecha al final.
        $query->orderByRaw('vence_at IS NULL')->orderBy('vence_at')->orderBy('id');

        return $this->generateViewSetList($request, $query, [], [], [], LoteResource::class);
    }

    public function entradas(RegistrarEntradaRequest $request): JsonResponse
    {
        $movimiento = $request->validated('movimiento');

        return $this->respuesta($this->inventario->entrada(
            $request->user()->sedeOperativa(),
            $movimiento['lineas'], $movimiento['referencia'] ?? null, $movimiento['observacion'] ?? null, $request->user(),
        ));
    }

    public function salidas(RegistrarSalidaRequest $request): JsonResponse
    {
        $movimiento = $request->validated('movimiento');

        // Una salida manual sí puede sacar lotes vencidos (es la merma).
        return $this->respuesta($this->inventario->salida(
            $request->user()->sedeOperativa(),
            $movimiento['lineas'], $movimiento['motivo'], $movimiento['referencia'] ?? null, $movimiento['observacion'] ?? null, $request->user(),
            vencidos: true,
        ));
    }

    public function ajustes(RegistrarAjusteRequest $request): JsonResponse
    {
        $movimiento = $request->validated('movimiento');

        return $this->respuesta($this->inventario->ajuste(
            $request->user()->sedeOperativa(),
            $movimiento['lineas'], $movimiento['referencia'] ?? null, $movimiento['observacion'] ?? null, $request->user(),
        ));
    }

    /**
     * Desde la sede del usuario hacia otra.
     */
    public function traslados(RegistrarTrasladoRequest $request): JsonResponse
    {
        $movimiento = $request->validated('movimiento');

        return $this->respuesta($this->inventario->traslado(
            $request->user()->sedeOperativa(),
            (int) $movimiento['sede_destino_id'],
            $movimiento['lineas'],
            $movimiento['referencia'] ?? null,
            $movimiento['observacion'] ?? null,
            $request->user(),
        ));
    }

    /**
     * Completa lo que se olvidó cargar en una entrada manual o de alta de
     * producto. La de una compra se corrige desde la compra.
     */
    public function corregir(CorregirEntradaRequest $request, MovimientoInventario $movimiento): JsonResponse
    {
        $corregible = $movimiento->tipo === MovimientoInventario::ENTRADA
            && $movimiento->compra_id === null
            && in_array($movimiento->motivo, [null, MovimientoInventario::MOTIVO_ALTA_PRODUCTO], true);

        if (! $corregible) {
            return response()->json(['message' => 'Sólo se corrigen entradas manuales o de alta de producto.'], 409);
        }

        $movimiento = $this->inventario->corregirEntrada($movimiento, $request->validated('entrada', []));
        $movimiento->load([...self::RELACIONES, 'usuario:id,name', 'sede:id,nombre', 'sedeRelacionada:id,nombre']);

        return response()->json(new MovimientoResource($movimiento));
    }

    /**
     * Le da lote al stock que quedó sin lote en la sede del usuario.
     */
    public function asignarLote(AsignarLoteRequest $request): JsonResponse
    {
        $datos = $request->validated('lote');
        $variante = Variante::query()->with('producto:id,maneja_lotes')->findOrFail($datos['variante_id']);

        if (! $variante->producto->maneja_lotes) {
            return response()->json(['message' => 'Este producto no maneja lotes: activalo primero editando el producto.'], 409);
        }

        $lote = $this->inventario->asignarLote($variante->id, $request->user()->sedeOperativa(), $datos['codigo'], $datos['vence_at'] ?? null, (float) $datos['cantidad']);
        $lote->load(['sede:id,nombre', 'variante.producto:id,nombre', 'variante.unidad:id,abreviatura', 'variante.color:id,nombre,hexadecimal']);

        return response()->json(new LoteResource($lote), 201);
    }

    /**
     * Los movimientos creados. Vacío es válido (un conteo que coincidió en
     * todo no genera movimientos).
     *
     * @param  Collection<int, MovimientoInventario>  $movimientos
     */
    private function respuesta(Collection $movimientos): JsonResponse
    {
        $movimientos->load([...self::RELACIONES, 'usuario:id,name', 'sede:id,nombre', 'sedeRelacionada:id,nombre']);

        return response()->json([
            'data' => $movimientos->map(fn ($m) => (new MovimientoResource($m))->resolve(request())),
        ], 201);
    }
}
