<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\Archivo;
use App\Models\Categoria;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Variante;
use App\Services\ArchivosService;
use App\Services\Exportaciones;
use App\Services\Inventario;
use App\Support\Permisos;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductoController extends Controller
{
    public function __construct(private ArchivosService $archivos, private Inventario $inventario) {}

    public function index(Request $request): JsonResponse
    {
        $sedeId = $this->sedeConsultada($request);

        $query = Producto::query()
            ->with(['categoria:id,nombre', 'marca:id,nombre', 'portada'])
            ->withCount('variantes');

        if ($sedeId) {
            $query->withSum(['stocks as stock_total' => fn ($q) => $q->where('stocks.sede_id', $sedeId)], 'cantidad');
        } else {
            $query->withSum('variantes as stock_total', 'stock');
        }

        $this->filtrar($request, $query, $sedeId);

        return $this->generateViewSetList(
            $request,
            $query,
            ['activo'],
            [],
            ['id', 'nombre', 'precio', 'activo'],
            ProductoResource::class,
        );
    }

    /**
     * La lista de productos en Excel, con los mismos filtros que la pantalla
     * y una fila por presentación.
     */
    public function exportar(Request $request, Exportaciones $exportar): StreamedResponse
    {
        $sedeId = $this->sedeConsultada($request);

        $productos = Producto::query();
        $this->filtrar($request, $productos, $sedeId);
        if ($request->filled('activo')) {
            $productos->where('activo', $request->boolean('activo'));
        }

        // select antes del join: si no, el id de productos pisa el de la variante.
        $variantes = Variante::query()
            ->select('variantes.*')
            ->whereIn('variantes.producto_id', $productos->select('productos.id'))
            ->when($sedeId, fn (Builder $q) => $q->habilitadaEnSede($sedeId)->conStockDeSede($sedeId))
            ->with(['producto:id,nombre,precio,activo,categoria_id,marca_id', 'producto.categoria:id,nombre', 'producto.marca:id,nombre', 'unidad:id,abreviatura', 'color:id,nombre'])
            ->join('productos', 'productos.id', '=', 'variantes.producto_id')
            ->orderBy('productos.nombre')
            ->orderBy('variantes.id')
            ->get();

        $conCosto = Permisos::puede($request->user(), 'productos.update') || Permisos::puede($request->user(), 'reportes.ventas');
        $sede = $sedeId ? Sede::query()->whereKey($sedeId)->value('nombre') : null;

        return $exportar->descargar(
            $exportar->productos($variantes, $sede, $conCosto),
            'productos-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * Por defecto, los productos de la sede del usuario (los que vende y su
     * stock ahí); con `sede_id=0`, todo el catálogo y el stock total.
     */
    private function sedeConsultada(Request $request): int
    {
        return $request->has('sede_id') ? $request->integer('sede_id') : (int) $request->user()->sede_id;
    }

    /**
     * Los filtros de la lista (sede, categoría con sus subcategorías, marca y
     * búsqueda), compartidos con la exportación.
     */
    private function filtrar(Request $request, Builder $query, int $sedeId): void
    {
        if ($sedeId) {
            $query->whereHas('variantes', fn (Builder $v) => $v->habilitadaEnSede($sedeId));
        }

        // Filtrar por "Impermeabilizantes" trae también lo de sus subcategorías.
        if ($request->filled('categoria_id')) {
            $categoria = Categoria::find($request->integer('categoria_id'));
            $query->whereIn('categoria_id', $categoria
                ? [$categoria->id, ...$categoria->descendientesIds()]
                : []);
        }

        if ($request->filled('marca_id')) {
            $query->where('marca_id', $request->integer('marca_id'));
        }

        // Búsqueda por nombre o por SKU de cualquiera de sus variantes. Va
        // acá y no en generateViewSetList: ese sólo busca en columnas propias.
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('productos.nombre', 'like', $term)
                // SKU o código de barras de cualquiera de sus presentaciones.
                ->orWhereHas('variantes', fn (Builder $v) => $v
                    ->where('sku', 'like', $term)
                    ->orWhere('codigo_barras', 'like', $term)));
        }
    }

    public function store(StoreProductoRequest $request): JsonResponse
    {
        $producto = DB::transaction(function () use ($request) {
            $datos = $request->validated('producto');
            $producto = Producto::create($datos);
            $this->guardarRelaciones($producto, $datos, $request);

            return $producto;
        });

        return response()->json($this->conDetalle($producto), 201);
    }

    public function show(Producto $producto): JsonResponse
    {
        return response()->json($this->conDetalle($producto));
    }

    public function update(StoreProductoRequest $request, Producto $producto): JsonResponse
    {
        DB::transaction(function () use ($request, $producto) {
            $datos = $request->validated('producto');
            $producto->update($datos);
            $this->guardarRelaciones($producto, $datos, $request);
        });

        return response()->json($this->conDetalle($producto->refresh()));
    }

    public function destroy(Producto $producto): JsonResponse|Response
    {
        if ($producto->variantes()->where('stock', '!=', 0)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene presentaciones con stock. Desactívelo en su lugar.',
            ], 409);
        }

        // El historial de inventario no se pierde (FK restrict).
        if ($producto->variantes()->whereHas('movimientos')->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene historial de inventario. Desactívelo en su lugar.',
            ], 409);
        }

        DB::transaction(function () use ($producto) {
            // El polimórfico no tiene FK: el cascade de variantes no alcanza
            // a sus fotos.
            foreach ($producto->variantes as $variante) {
                $this->archivos->eliminarDe($variante);
            }
            $this->archivos->eliminarDe($producto);
            $producto->delete();
        });

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $datos  producto validado
     */
    private function guardarRelaciones(Producto $producto, array $datos, StoreProductoRequest $request): void
    {
        // Antes de tocar nada: una foto puede venir de una variante que se
        // quita en este mismo guardado.
        $fuentes = $this->fuentesDeFotos($datos);

        $this->archivos->sincronizar($producto, $datos['archivos'] ?? [], "productos/{$producto->id}", $fuentes);
        $variantes = $this->sincronizarVariantes($producto, $datos['variantes'], $fuentes);
        $this->sincronizarSedes($variantes, $datos['variantes'], $request);
        $this->registrarStockInicial($variantes, $datos, $request);
    }

    /**
     * El stock inicial de las variantes NUEVAS entra como una entrada de
     * inventario ("Alta de producto"), en la misma transacción: el stock
     * sigue saliendo sólo del libro de movimientos (historial y costo
     * promedio incluidos). Las variantes que ya existían se mueven desde
     * Inventario.
     *
     * @param  array<int, Variante>  $variantes  en el orden del payload
     * @param  array<string, mixed>  $datos
     */
    private function registrarStockInicial(array $variantes, array $datos, StoreProductoRequest $request): void
    {
        $lineas = [];
        foreach (array_values($datos['variantes']) as $i => $fila) {
            $cantidad = round((float) ($fila['stock_inicial'] ?? 0), 3);
            if (! empty($fila['id']) || $cantidad <= 0) {
                continue;
            }
            $lineas[] = [
                'variante_id' => $variantes[$i]->id,
                'cantidad' => $cantidad,
                // El de la variante o, si no se ajustó, el general del producto.
                'costo_unitario' => $fila['costo_unitario'] ?? $datos['costo_compra'],
                // Con lotes: el lote al que entra (el request ya lo exigió).
                ...(! empty($datos['maneja_lotes']) ? ['lote' => $fila['lote'], 'vence_at' => $fila['vence_at']] : []),
            ];
        }

        if ($lineas === []) {
            return;
        }

        // Entra en la sede de quien lo da de alta (el request ya exigió que
        // tenga una).
        $this->inventario->entrada(
            $request->user()->sedeOperativa(),
            $lineas,
            $datos['referencia_compra'] ?? null,
            null,
            $request->user(),
            MovimientoInventario::MOTIVO_ALTA_PRODUCTO,
        );
    }

    /**
     * Las filas con id se actualizan, las sin id se crean y las que ya no
     * vienen se borran (el request ya garantizó que ninguna tenía stock).
     *
     * Devuelve las variantes guardadas en el orden del payload (para
     * registrarles el stock inicial).
     *
     * @param  array<int, array<string, mixed>>  $variantes
     * @param  array<int, Archivo>  $fuentes
     * @return array<int, Variante>
     */
    private function sincronizarVariantes(Producto $producto, array $variantes, array $fuentes): array
    {
        $conservadas = collect($variantes)->pluck('id')->filter()->all();

        foreach ($producto->variantes()->whereNotIn('id', $conservadas)->get() as $quitada) {
            $this->archivos->eliminarDe($quitada);
            $quitada->delete();
        }

        $existentes = $producto->variantes()->get()->keyBy('id');

        // Primero se liberan los SKU de las que se editan: si A pasa a usar el
        // SKU viejo de B en el mismo guardado, el unique chocaría a mitad.
        // Lo mismo con el código de barras (también es único).
        foreach ($existentes as $variante) {
            $variante->update(['sku' => "~{$variante->id}", 'codigo_barras' => null]);
        }

        $guardadas = [];
        foreach (array_values($variantes) as $datos) {
            $campos = collect($datos)->only(['presentacion', 'unidad_id', 'color_id', 'sku', 'codigo_barras', 'precio', 'precio_mayor'])->all();

            $variante = empty($datos['id'])
                ? $producto->variantes()->create($campos)
                : tap($existentes[$datos['id']])->update($campos);

            $this->archivos->sincronizar($variante, $datos['archivos'] ?? [], "productos/{$producto->id}/variantes", $fuentes);
            $guardadas[] = $variante;
        }

        return $guardadas;
    }

    /**
     * Qué sedes venden cada presentación, a qué precio y con qué mínimo (la
     * fila de `stocks`; la cantidad no se toca: la mueve el inventario).
     *
     * Una presentación nueva sin configuración se habilita en la sede de
     * quien la crea; las sedes que no vienen en el payload no cambian.
     *
     * @param  array<int, Variante>  $variantes  en el orden del payload
     * @param  array<int, array<string, mixed>>  $datos
     */
    private function sincronizarSedes(array $variantes, array $datos, StoreProductoRequest $request): void
    {
        foreach (array_values($datos) as $i => $fila) {
            $variante = $variantes[$i];
            $sedes = $fila['sedes'] ?? null;

            if (empty($sedes)) {
                if (empty($fila['id']) && $request->user()->sede_id) {
                    $sedes = [['sede_id' => $request->user()->sede_id, 'activo' => true, 'precio' => null, 'stock_minimo' => 0]];
                } else {
                    continue;
                }
            }

            foreach ($sedes as $config) {
                // Sin firstOrNew: llena las claves por asignación masiva y Stock
                // no tiene fillable a propósito.
                $stock = Stock::query()->where('variante_id', $variante->id)->where('sede_id', $config['sede_id'])->first()
                    ?? (new Stock)->forceFill(['variante_id' => $variante->id, 'sede_id' => $config['sede_id'], 'cantidad' => 0]);
                $stock->forceFill([
                    'activo' => (bool) $config['activo'],
                    'precio' => $config['precio'] ?? null,
                    'stock_minimo' => $config['stock_minimo'] ?? 0,
                ])->save();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<int, Archivo>
     */
    private function fuentesDeFotos(array $datos): array
    {
        $ids = collect($datos['archivos'] ?? [])
            ->merge(collect($datos['variantes'])->flatMap(fn ($v) => $v['archivos'] ?? []))
            ->pluck('id')
            ->filter()
            ->unique();

        return $ids->isEmpty() ? [] : Archivo::query()->whereKey($ids)->get()->keyBy('id')->all();
    }

    /**
     * Sin el envoltorio `data` de los Resources: el resto de la API devuelve
     * el objeto directo y el front lo espera así.
     *
     * @return array<string, mixed>
     */
    private function conDetalle(Producto $producto): array
    {
        return (new ProductoResource($producto->load([
            'categoria:id,nombre',
            'marca:id,nombre',
            'archivos',
            'variantes' => fn ($q) => $q->orderBy('variantes.id')->withExists('movimientos'),
            'variantes.unidad:id,nombre,abreviatura,fraccionable',
            'variantes.color:id,nombre,hexadecimal',
            'variantes.stocks' => fn ($q) => $q->orderBy('sede_id'),
            'variantes.stocks.sede:id,nombre',
            'variantes.archivos',
        ])))->resolve(request());
    }
}
