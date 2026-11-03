<?php

namespace App\Services;

use App\Models\Lote;
use App\Models\MovimientoInventario;
use App\Models\Stock;
use App\Models\User;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * El ÚNICO lugar que mueve el stock: el de cada sede (`stocks`), el total de
 * la empresa (`variantes.stock`) y, en los productos que manejan lotes, el
 * de cada lote (`lotes`). Cada cambio deja su movimiento en el libro
 * (movimiento_inventarios, con el detalle por lote en movimiento_lotes)
 * dentro de la misma transacción: stock e historial nunca se desincronizan.
 *
 * Las filas se bloquean (FOR UPDATE) mientras se registra: dos personas
 * descontando la misma presentación a la vez esperan su turno en vez de
 * pisarse el stock.
 *
 * Las cantidades son decimales (3 cifras) por las unidades fraccionables;
 * que una bolsa no se venda por la mitad lo validan los requests.
 *
 * Lotes, en una línea (sólo productos con `maneja_lotes`):
 * - Entrada: `lote` + `vence_at` (toda la cantidad a ese lote) o `lotes`
 *   [{codigo, vence_at, cantidad}] (devoluciones y traslados, que vuelven a
 *   los lotes de los que salieron).
 * - Salida: `lote_id` para sacar de un lote puntual; si no, sale del que
 *   vence primero (FEFO). Las ventas y traslados nunca usan lotes vencidos.
 * - Ajuste: lo que falta sale por FEFO (vencidos incluidos); lo que sobra
 *   entra al `lote` + `vence_at` de la línea.
 */
class Inventario
{
    /** Por debajo de esto, una diferencia es ruido de redondeo. */
    private const EPSILON = 0.0005;

    /**
     * Compra o reposición (o la devolución de una venta cancelada, con
     * `$motivo`) en una sede. Recalcula el costo promedio ponderado, que es
     * de la empresa: la misma bolsa cuesta lo mismo en cualquier sede.
     *
     * `$extra` son columnas adicionales del movimiento (p. ej. `pedido_id`).
     *
     * @param  array<int, array<string, mixed>>  $lineas
     * @param  array<string, mixed>  $extra
     * @return Collection<int, MovimientoInventario>
     */
    public function entrada(int $sedeId, array $lineas, ?string $referencia, ?string $observacion, ?User $usuario, ?string $motivo = null, array $extra = [], ?string $grupo = null): Collection
    {
        return $this->registrar($sedeId, $lineas, function (Variante $variante, Stock $stock, array $linea) use ($motivo, $extra) {
            $cantidad = round((float) $linea['cantidad'], 3);
            // null = costo desconocido (p. ej. devolver una venta de stock que
            // entró por un ajuste, o un traslado): no se toca el promedio. Un
            // 0 lo arrastraría hacia abajo sin que nadie haya comprado nada gratis.
            $costo = isset($linea['costo_unitario']) ? (float) $linea['costo_unitario'] : null;

            // Promedio ponderado sobre el stock de TODA la empresa. Con stock
            // en 0 (o sin costo previo) el costo anterior no pesa.
            if ($costo !== null) {
                $total = max(0.0, (float) $variante->stock);
                $anterior = $variante->costo_promedio !== null ? (float) $variante->costo_promedio : null;
                $variante->costo_promedio = $anterior === null || $total < self::EPSILON
                    ? $costo
                    : round((($total * $anterior) + ($cantidad * $costo)) / ($total + $cantidad), 4);
            }

            return [
                ...$extra,
                'tipo' => MovimientoInventario::ENTRADA,
                'cantidad' => $cantidad,
                'costo_unitario' => $costo,
                'motivo' => $motivo,
            ];
        }, $referencia, $observacion, $usuario, $grupo);
    }

    /**
     * Merma, daño, regalo, venta… Nunca deja el stock de la sede en negativo.
     * `$vencidos`: si puede salir de lotes vencidos (una merma sí; una venta
     * o un traslado, no).
     *
     * @param  array<int, array<string, mixed>>  $lineas
     * @param  array<string, mixed>  $extra  columnas adicionales del movimiento (p. ej. `pedido_id`)
     * @return Collection<int, MovimientoInventario>
     */
    public function salida(int $sedeId, array $lineas, string $motivo, ?string $referencia, ?string $observacion, ?User $usuario, array $extra = [], ?string $grupo = null, bool $vencidos = false): Collection
    {
        return $this->registrar($sedeId, $lineas, fn (Variante $variante, Stock $stock, array $linea) => [
            ...$extra,
            'tipo' => MovimientoInventario::SALIDA,
            'cantidad' => -round((float) $linea['cantidad'], 3),
            'motivo' => $motivo,
        ], $referencia, $observacion, $usuario, $grupo, $vencidos);
    }

    /**
     * Conteo físico en una sede: se informa el stock REAL y se registra la
     * diferencia. Las presentaciones que ya coinciden no generan movimiento.
     *
     * @param  array<int, array<string, mixed>>  $lineas
     * @return Collection<int, MovimientoInventario>
     */
    public function ajuste(int $sedeId, array $lineas, ?string $referencia, ?string $observacion, ?User $usuario): Collection
    {
        return $this->registrar($sedeId, $lineas, function (Variante $variante, Stock $stock, array $linea) {
            $diferencia = round((float) $linea['stock_real'] - $stock->cantidad, 3);

            return abs($diferencia) < self::EPSILON ? null : [
                'tipo' => MovimientoInventario::AJUSTE,
                'cantidad' => $diferencia,
                'motivo' => MovimientoInventario::MOTIVO_CONTEO,
            ];
        }, $referencia, $observacion, $usuario, null, true);
    }

    /**
     * Mercadería que pasa de una sede a otra: una salida en el origen y una
     * entrada en el destino, con el mismo grupo y cada una apuntando a la
     * otra sede. Los lotes viajan con la mercadería (mismo código y
     * vencimiento). El total de la empresa y el costo promedio no cambian.
     *
     * @param  array<int, array{variante_id: int, cantidad: numeric}>  $lineas
     * @return Collection<int, MovimientoInventario>
     */
    public function traslado(int $origenId, int $destinoId, array $lineas, ?string $referencia, ?string $observacion, ?User $usuario): Collection
    {
        if ($origenId === $destinoId) {
            throw ValidationException::withMessages([
                'movimiento.sede_destino_id' => 'El destino tiene que ser otra sede.',
            ]);
        }

        return DB::transaction(function () use ($origenId, $destinoId, $lineas, $referencia, $observacion, $usuario) {
            $grupo = (string) Str::uuid();
            $salidas = $this->salida($origenId, $lineas, MovimientoInventario::MOTIVO_TRASLADO_SALIDA, $referencia, $observacion, $usuario,
                ['sede_relacionada_id' => $destinoId], $grupo);

            // Cada línea entra a los mismos lotes de los que salió.
            $porVariante = $salidas->load('lotes')->keyBy('variante_id');
            $entrantes = array_map(fn ($l) => [
                ...$l,
                'costo_unitario' => null,
                'lotes' => self::lotesDe($porVariante[(int) $l['variante_id']] ?? null),
            ], $lineas);

            $entradas = $this->entrada($destinoId, $entrantes, $referencia, $observacion, $usuario,
                MovimientoInventario::MOTIVO_TRASLADO_ENTRADA, ['sede_relacionada_id' => $origenId], $grupo);

            return $salidas->merge($entradas);
        });
    }

    /**
     * Los lotes de un movimiento como líneas de entrada [{codigo, vence_at,
     * cantidad}]: para devolver la mercadería al lote del que salió.
     *
     * @return array<int, array{codigo: string, vence_at: ?string, cantidad: float}>
     */
    public static function lotesDe(?MovimientoInventario $movimiento): array
    {
        if (! $movimiento) {
            return [];
        }

        return $movimiento->lotes->map(fn (Lote $lote) => [
            'codigo' => $lote->codigo,
            'vence_at' => $lote->vence_at?->toDateString(),
            'cantidad' => abs((float) $lote->pivot->cantidad),
        ])->values()->all();
    }

    /**
     * `$calcular` devuelve los datos del movimiento (con `cantidad` con signo)
     * o null si no hay nada que registrar para esa línea.
     *
     * @param  array<int, array<string, mixed>>  $lineas
     * @param  callable(Variante, Stock, array<string, mixed>): (array<string, mixed>|null)  $calcular
     * @return Collection<int, MovimientoInventario>
     */
    private function registrar(int $sedeId, array $lineas, callable $calcular, ?string $referencia, ?string $observacion, ?User $usuario, ?string $grupo = null, bool $vencidos = false): Collection
    {
        return DB::transaction(function () use ($sedeId, $lineas, $calcular, $referencia, $observacion, $usuario, $grupo, $vencidos) {
            $grupo ??= (string) Str::uuid();
            $ids = collect($lineas)->pluck('variante_id')->map(fn ($id) => (int) $id)->unique()->values();

            // Bloqueadas en orden de id: dos documentos con las mismas
            // presentaciones en distinto orden no se trancan entre sí.
            $variantes = Variante::query()
                ->with('producto:id,maneja_lotes')
                ->whereKey($ids->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // La primera vez que una presentación pasa por una sede todavía
            // no tiene fila: se crea en 0 (el unique evita duplicarla).
            Stock::query()->insertOrIgnore($ids->map(fn ($id) => [
                'variante_id' => $id,
                'sede_id' => $sedeId,
                'cantidad' => 0,
            ])->all());

            $stocks = Stock::query()
                ->where('sede_id', $sedeId)
                ->whereIn('variante_id', $ids->all())
                ->orderBy('variante_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('variante_id');

            $lotes = Lote::query()
                ->where('sede_id', $sedeId)
                ->whereIn('variante_id', $ids->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('variante_id');

            $movimientos = new Collection;
            $errores = [];

            foreach (array_values($lineas) as $i => $linea) {
                $variante = $variantes[(int) $linea['variante_id']];
                $stock = $stocks[(int) $linea['variante_id']];
                $datos = $calcular($variante, $stock, $linea);
                if ($datos === null) {
                    continue;
                }

                $nuevo = round($stock->cantidad + $datos['cantidad'], 3);
                if ($nuevo < -self::EPSILON) {
                    $errores["movimiento.lineas.{$i}.cantidad"] = 'Stock insuficiente en esta sede: hay '.self::formatear($stock->cantidad).'.';

                    continue;
                }
                $nuevo = max(0.0, $nuevo);

                // [[Lote, cantidad con signo]]: de dónde sale o a dónde entra.
                $asignacion = [];
                if ($variante->producto->maneja_lotes) {
                    $delVariante = $lotes[$variante->id] ?? new Collection;
                    $asignacion = $datos['cantidad'] > 0
                        ? $this->asignarEntrada($variante, $sedeId, $linea, $datos['cantidad'], $delVariante, $i, $errores)
                        : $this->asignarSalida($linea, -$datos['cantidad'], $delVariante, $vencidos || $datos['tipo'] === MovimientoInventario::AJUSTE, $i, $errores);
                    $lotes[$variante->id] = $delVariante;

                    if ($asignacion === null) {
                        continue;
                    }
                }

                $stock->forceFill(['cantidad' => $nuevo])->save();
                // stock y costo_promedio no son fillable a propósito.
                $variante->forceFill(['stock' => round((float) $variante->stock + $datos['cantidad'], 3)])->save();

                $movimiento = MovimientoInventario::create([
                    ...$datos,
                    'grupo' => $grupo,
                    'variante_id' => $variante->id,
                    'sede_id' => $sedeId,
                    'stock_resultante' => $nuevo,
                    'referencia' => $referencia,
                    'observacion' => $observacion,
                    'user_id' => $usuario?->id,
                ]);

                foreach ($asignacion as [$lote, $cantidad]) {
                    $lote->forceFill(['cantidad' => round($lote->cantidad + $cantidad, 3)])->save();
                    $movimiento->lotes()->attach($lote->id, ['cantidad' => $cantidad]);
                }

                $movimientos->push($movimiento);
            }

            // Todo o nada: con una línea con error no se registra ninguna.
            if ($errores !== []) {
                throw ValidationException::withMessages($errores);
            }

            return $movimientos;
        });
    }

    /**
     * A qué lote(s) entra la cantidad. Crea el lote si es nuevo en la sede.
     * null (con el error anotado) si falta el lote o no cuadra.
     *
     * @param  Collection<int, Lote>  $lotes  los de la presentación en la sede (se le agregan los nuevos)
     * @param  array<string, string>  $errores
     * @return array<int, array{0: Lote, 1: float}>|null
     */
    private function asignarEntrada(Variante $variante, int $sedeId, array $linea, float $cantidad, Collection $lotes, int $i, array &$errores): ?array
    {
        $pedidos = $linea['lotes'] ?? null;
        if (! $pedidos && filled($linea['lote'] ?? null)) {
            $pedidos = [['codigo' => $linea['lote'], 'vence_at' => $linea['vence_at'] ?? null, 'cantidad' => $cantidad]];
        }

        if (! $pedidos) {
            $errores["movimiento.lineas.{$i}.lote"] = 'Este producto maneja lotes: indicá el lote y su vencimiento.';

            return null;
        }

        if (abs(collect($pedidos)->sum(fn ($p) => (float) $p['cantidad']) - $cantidad) > self::EPSILON) {
            $errores["movimiento.lineas.{$i}.lote"] = 'La suma de los lotes no coincide con la cantidad.';

            return null;
        }

        $asignacion = [];
        foreach ($pedidos as $pedido) {
            $codigo = self::normalizarLote($pedido['codigo']);
            $vence = filled($pedido['vence_at'] ?? null) ? Carbon::parse($pedido['vence_at'])->startOfDay() : null;
            $lote = $lotes->first(fn (Lote $l) => $l->codigo === $codigo);

            if ($lote && $vence && $lote->vence_at && ! $lote->vence_at->equalTo($vence)) {
                $errores["movimiento.lineas.{$i}.lote"] = "El lote {$codigo} ya está registrado con vencimiento {$lote->vence_at->format('d/m/Y')}.";

                return null;
            }

            if (! $lote) {
                $lote = new Lote;
                $lote->forceFill([
                    'variante_id' => $variante->id,
                    'sede_id' => $sedeId,
                    'codigo' => $codigo,
                    'vence_at' => $vence,
                    'cantidad' => 0,
                ])->save();
                $lotes->push($lote);
            } elseif (! $lote->vence_at && $vence) {
                // Un lote cargado sin fecha la recibe la primera vez que llega.
                $lote->forceFill(['vence_at' => $vence])->save();
            }

            $asignacion[] = [$lote, round((float) $pedido['cantidad'], 3)];
        }

        return $asignacion;
    }

    /**
     * De qué lote(s) sale la cantidad: el elegido (`lote_id`) o, si no, los
     * que vencen primero. null (con el error anotado) si no alcanza.
     *
     * @param  Collection<int, Lote>  $lotes
     * @param  array<string, string>  $errores
     * @return array<int, array{0: Lote, 1: float}>|null
     */
    private function asignarSalida(array $linea, float $cantidad, Collection $lotes, bool $vencidos, int $i, array &$errores): ?array
    {
        if (filled($linea['lote_id'] ?? null)) {
            $lote = $lotes->first(fn (Lote $l) => $l->id === (int) $linea['lote_id']);

            if (! $lote || $lote->cantidad + self::EPSILON < $cantidad) {
                $errores["movimiento.lineas.{$i}.cantidad"] = 'El lote no alcanza: hay '.self::formatear($lote?->cantidad ?? 0).'.';

                return null;
            }

            return [[$lote, -$cantidad]];
        }

        // FEFO: primero el que vence antes; los sin fecha al final.
        $disponibles = $lotes
            ->filter(fn (Lote $l) => $l->cantidad > self::EPSILON && ($vencidos || ! $l->vencido()))
            ->sortBy(fn (Lote $l) => [$l->vence_at?->timestamp ?? PHP_INT_MAX, $l->id])
            ->values();

        $asignacion = [];
        $falta = $cantidad;
        foreach ($disponibles as $lote) {
            if ($falta < self::EPSILON) {
                break;
            }
            $toma = round(min($falta, $lote->cantidad), 3);
            $asignacion[] = [$lote, -$toma];
            $falta = round($falta - $toma, 3);
        }

        if ($falta > self::EPSILON) {
            $vigente = $disponibles->sum('cantidad');
            $hayVencidos = ! $vencidos && $lotes->contains(fn (Lote $l) => $l->cantidad > self::EPSILON && $l->vencido());
            // Sin vencidos que expliquen la diferencia, es stock que quedó
            // sin lote (entró antes de activar lotes en el producto).
            $errores["movimiento.lineas.{$i}.cantidad"] = $hayVencidos
                ? 'Sólo hay '.self::formatear($vigente).' sin vencer: lo demás está vencido (sacalo como merma).'
                : 'Sólo hay '.self::formatear($vigente).' en lotes. Si hay stock sin lote, asignáselo en la ficha del producto (Lotes en tu sede).';

            return null;
        }

        return $asignacion;
    }

    /**
     * Completa o corrige los datos de una entrada que no mueven stock: costo
     * unitario, referencia (n° de factura / guía) y el código y vencimiento
     * de sus lotes. Para lo que se olvidó cargar; la cantidad no se toca.
     *
     * Cambiar el costo recalcula el costo promedio de la presentación desde
     * su historial. Las ventas ya hechas guardan el costo de su momento y no
     * cambian.
     *
     * @param  array{costo_unitario?: float|string|null, referencia?: ?string, lotes?: array<int, array{id: int, codigo: string, vence_at: ?string}>}  $datos
     */
    public function corregirEntrada(MovimientoInventario $movimiento, array $datos): MovimientoInventario
    {
        return DB::transaction(function () use ($movimiento, $datos) {
            $variante = Variante::query()->lockForUpdate()->findOrFail($movimiento->variante_id);

            if (array_key_exists('costo_unitario', $datos)) {
                $movimiento->costo_unitario = $datos['costo_unitario'] !== null ? round((float) $datos['costo_unitario'], 2) : null;
            }
            if (array_key_exists('referencia', $datos)) {
                $movimiento->referencia = filled($datos['referencia']) ? trim($datos['referencia']) : null;
            }

            $costoCambio = $movimiento->isDirty('costo_unitario');
            $movimiento->save();

            $errores = [];
            foreach ($datos['lotes'] ?? [] as $i => $dato) {
                /** @var Lote|null $lote */
                $lote = $movimiento->lotes()->whereKey($dato['id'])->first();
                if (! $lote) {
                    $errores["entrada.lotes.{$i}.codigo"] = 'Ese lote no es de esta entrada.';

                    continue;
                }

                $codigo = self::normalizarLote($dato['codigo']);
                $repetido = Lote::query()
                    ->where('variante_id', $lote->variante_id)
                    ->where('sede_id', $lote->sede_id)
                    ->where('codigo', $codigo)
                    ->whereKeyNot($lote->id)
                    ->exists();
                if ($repetido) {
                    $errores["entrada.lotes.{$i}.codigo"] = "Ya hay otro lote {$codigo} de esta presentación en la sede.";

                    continue;
                }

                $lote->forceFill([
                    'codigo' => $codigo,
                    'vence_at' => filled($dato['vence_at'] ?? null) ? Carbon::parse($dato['vence_at'])->startOfDay() : null,
                ])->save();
            }

            if ($errores) {
                throw ValidationException::withMessages($errores);
            }

            if ($costoCambio) {
                $variante->costo_promedio = $this->costoPromedioDesdeHistorial($variante);
                $variante->save();
            }

            return $movimiento->refresh();
        });
    }

    /**
     * Le da lote a stock que no lo tiene: lo que entró antes de activar lotes
     * en el producto (p. ej. el stock inicial). No mueve stock ni crea un
     * movimiento: sólo reparte lo que ya hay en la sede entre lotes. Si el
     * código ya existe, suma a ese lote.
     */
    public function asignarLote(int $varianteId, int $sedeId, string $codigo, ?string $venceAt, float $cantidad): Lote
    {
        return DB::transaction(function () use ($varianteId, $sedeId, $codigo, $venceAt, $cantidad) {
            $stock = Stock::query()->where('variante_id', $varianteId)->where('sede_id', $sedeId)->lockForUpdate()->first();
            $lotes = Lote::query()->where('variante_id', $varianteId)->where('sede_id', $sedeId)->lockForUpdate()->get();

            $sinLote = round((float) ($stock?->cantidad ?? 0) - (float) $lotes->sum('cantidad'), 3);
            $cantidad = round($cantidad, 3);

            if ($sinLote < self::EPSILON) {
                throw ValidationException::withMessages(['lote.cantidad' => 'Todo el stock de esta presentación en la sede ya tiene lote.']);
            }
            if ($cantidad - $sinLote > self::EPSILON) {
                throw ValidationException::withMessages(['lote.cantidad' => 'Sólo hay '.self::formatear($sinLote).' sin lote.']);
            }

            $codigo = self::normalizarLote($codigo);
            $vence = filled($venceAt) ? Carbon::parse($venceAt)->startOfDay() : null;
            $lote = $lotes->first(fn (Lote $l) => $l->codigo === $codigo);

            if ($lote && $vence && $lote->vence_at && ! $lote->vence_at->equalTo($vence)) {
                throw ValidationException::withMessages(['lote.codigo' => "El lote {$codigo} ya está registrado con vencimiento {$lote->vence_at->format('d/m/Y')}."]);
            }

            $lote ??= (new Lote)->forceFill([
                'variante_id' => $varianteId,
                'sede_id' => $sedeId,
                'codigo' => $codigo,
                'cantidad' => 0,
            ]);
            $lote->forceFill([
                'vence_at' => $lote->vence_at ?? $vence,
                'cantidad' => round((float) $lote->cantidad + $cantidad, 3),
            ])->save();

            return $lote;
        });
    }

    /**
     * Repite, movimiento por movimiento, el mismo promedio ponderado que hace
     * entrada(): el stock de la empresa es la suma de lo movido hasta ahí.
     */
    private function costoPromedioDesdeHistorial(Variante $variante): ?float
    {
        $stock = 0.0;
        $costo = null;

        $movimientos = MovimientoInventario::query()
            ->where('variante_id', $variante->id)
            ->orderBy('id')
            ->get(['tipo', 'cantidad', 'costo_unitario']);

        foreach ($movimientos as $m) {
            if ($m->tipo === MovimientoInventario::ENTRADA && $m->costo_unitario !== null) {
                $total = max(0.0, $stock);
                $unitario = (float) $m->costo_unitario;
                $costo = $costo === null || $total < self::EPSILON
                    ? $unitario
                    : round((($total * $costo) + ($m->cantidad * $unitario)) / ($total + $m->cantidad), 4);
            }
            $stock += $m->cantidad;
        }

        return $costo;
    }

    /**
     * Los códigos se comparan sin espacios ni mayúsculas: "l-2301 " es "L-2301".
     */
    public static function normalizarLote(string $codigo): string
    {
        return mb_strtoupper(trim($codigo));
    }

    /**
     * "12" y no "12.000"; "2.5" y no "2.500".
     */
    public static function formatear(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 3, '.', ''), '0'), '.');
    }
}
