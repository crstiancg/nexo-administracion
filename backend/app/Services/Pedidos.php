<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Variante;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El único lugar que cambia el estado de un pedido. Los totales se calculan
 * acá (nunca se confía en los del navegador) y el stock se mueve sólo a
 * través de App\Services\Inventario.
 */
class Pedidos
{
    public function __construct(private Inventario $inventario, private Cajas $cajas) {}

    /**
     * Crea o reemplaza los ítems de un pedido PENDIENTE y recalcula totales.
     *
     * @param  array<string, mixed>  $datos  pedido validado (con `items`)
     */
    public function guardar(Pedido $pedido, array $datos, ?User $usuario): Pedido
    {
        return DB::transaction(function () use ($pedido, $datos, $usuario) {
            $nuevo = ! $pedido->exists;

            $pedido->fill($datos);
            if ($nuevo) {
                // Vende la sede del usuario: de ahí sale la mercadería.
                $pedido->forceFill([
                    'estado' => Pedido::PENDIENTE,
                    'sede_id' => $usuario?->sedeOperativa(),
                    'user_id' => $usuario?->id,
                    'subtotal' => 0,
                    'total' => 0,
                ]);
            }
            $pedido->save();

            if ($nuevo) {
                $pedido->forceFill(['codigo' => 'P-'.str_pad((string) $pedido->id, 6, '0', STR_PAD_LEFT)])->save();
            }

            // Pendiente todavía no tocó el stock: los ítems se reemplazan.
            $pedido->items()->delete();
            $subtotal = 0.0;

            // Por mayor: la línea se cobra al precio por mayor de la
            // presentación (lo aplica solo un cliente mayorista o lo elige el
            // vendedor en el punto de venta).
            $preciosMayor = Variante::query()
                ->whereKey(collect($datos['items'])->pluck('variante_id'))
                ->whereNotNull('precio_mayor')
                ->pluck('precio_mayor', 'id');

            foreach ($datos['items'] as $item) {
                $linea = round((float) $item['cantidad'] * (float) $item['precio_unitario'], 2);
                $subtotal += $linea;

                $pedido->items()->create([
                    'variante_id' => $item['variante_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'por_mayor' => $preciosMayor->has($item['variante_id'])
                        && abs((float) $preciosMayor->get($item['variante_id']) - (float) $item['precio_unitario']) < 0.005,
                    'subtotal' => $linea,
                ]);
            }

            $descuento = (float) ($datos['descuento'] ?? 0);
            $total = round($subtotal - $descuento, 2);

            // Un pendiente puede tener un adelanto: el total no puede quedar
            // por debajo de lo ya cobrado (habría que devolver primero).
            $pagado = $nuevo ? 0.0 : $pedido->pagado();
            if ($total < $pagado) {
                throw ValidationException::withMessages([
                    'pedido.items' => 'El total quedaría por debajo de lo ya pagado (S/ '.number_format($pagado, 2).'): devolvé la diferencia primero.',
                ]);
            }

            $pedido->forceFill([
                'subtotal' => round($subtotal, 2),
                'total' => $total,
            ])->save();

            return $pedido;
        });
    }

    /**
     * Descuenta el stock de la sede del pedido (salida con motivo "venta") y
     * congela el costo de cada ítem. Todo o nada: con un ítem sin stock no se confirma nada.
     */
    public function confirmar(Pedido $pedido, ?User $usuario): Pedido
    {
        return DB::transaction(function () use ($pedido, $usuario) {
            // Bloqueado: dos clics en "Confirmar" no descuentan dos veces.
            $pedido = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            $this->exigirEstado($pedido, [Pedido::PENDIENTE], 'confirmar');

            $items = $pedido->items()->with('variante.producto:id,nombre')->orderBy('id')->get();

            try {
                $this->inventario->salida(
                    $pedido->sede_id,
                    $items->map(fn ($i) => ['variante_id' => $i->variante_id, 'cantidad' => $i->cantidad])->all(),
                    MovimientoInventario::MOTIVO_VENTA,
                    $pedido->codigo,
                    null,
                    $usuario,
                    ['pedido_id' => $pedido->id],
                );
            } catch (ValidationException $e) {
                // Las líneas del inventario son los ítems en el mismo orden:
                // el error se muestra en el ítem del pedido, con el nombre del
                // producto (desde el listado no se ve qué ítem es el "1").
                throw ValidationException::withMessages(collect($e->errors())
                    ->mapWithKeys(function ($mensajes, $clave) use ($items) {
                        preg_match('/^movimiento\.lineas\.(\d+)\./', $clave, $m);
                        $variante = isset($m[1]) ? $items->get((int) $m[1])?->variante : null;
                        $nombre = $variante ? trim($variante->producto->nombre.' '.$variante->presentacion) : null;

                        return [str_replace('movimiento.lineas.', 'pedido.items.', $clave) => array_map(
                            fn ($mensaje) => $nombre ? "{$nombre}: {$mensaje}" : $mensaje,
                            $mensajes,
                        )];
                    })
                    ->all());
            }

            // Costo del momento de la venta (las variantes siguen bloqueadas
            // por la salida de arriba: nadie lo cambió en el medio).
            $costos = Variante::query()->whereKey($items->pluck('variante_id'))->pluck('costo_promedio', 'id');
            foreach ($items as $item) {
                $item->forceFill(['costo_unitario' => $costos[$item->variante_id] ?? null])->save();
            }

            $pedido->forceFill(['estado' => Pedido::CONFIRMADO, 'confirmado_at' => now()])->save();

            return $pedido;
        });
    }

    public function entregar(Pedido $pedido): Pedido
    {
        return DB::transaction(function () use ($pedido) {
            $pedido = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            $this->exigirEstado($pedido, [Pedido::CONFIRMADO], 'marcar como entregado');

            // No se entrega mercadería sin cobrar.
            if (($saldo = $pedido->saldo()) > 0) {
                throw ValidationException::withMessages([
                    'estado' => 'Falta cobrar S/ '.number_format($saldo, 2).' antes de entregar.',
                ])->status(409);
            }

            $pedido->forceFill(['estado' => Pedido::ENTREGADO, 'entregado_at' => now()])->save();

            return $pedido;
        });
    }

    /**
     * Un pedido confirmado devuelve su mercadería al stock (entrada con el
     * costo congelado de la venta). Uno entregado no se cancela: eso es una
     * devolución, otro flujo.
     */
    public function cancelar(Pedido $pedido, ?User $usuario): Pedido
    {
        return DB::transaction(function () use ($pedido, $usuario) {
            $pedido = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            $this->exigirEstado($pedido, [Pedido::PENDIENTE, Pedido::CONFIRMADO], 'cancelar');

            // La plata se devuelve explícitamente (queda en la caja), no se
            // "cancela" junto con el pedido.
            if (($pagado = $pedido->pagado()) > 0) {
                throw ValidationException::withMessages([
                    'estado' => 'Tiene S/ '.number_format($pagado, 2).' cobrados: devolvé los pagos antes de cancelar.',
                ])->status(409);
            }

            if ($pedido->estado === Pedido::CONFIRMADO) {
                $this->devolverStock($pedido, $usuario);
            }

            $pedido->forceFill(['estado' => Pedido::CANCELADO, 'cancelado_at' => now()])->save();

            return $pedido;
        });
    }

    /**
     * Deshace una venta ya hecha (confirmada o entregada, también las del
     * punto de venta): devuelve lo cobrado desde la caja abierta de hoy y la
     * mercadería a los lotes de los que salió. Con boleta o factura vigente
     * no: eso se anula ante SUNAT con nota de crédito.
     */
    public function anular(Pedido $pedido, string $motivo, User $usuario): Pedido
    {
        return DB::transaction(function () use ($pedido, $motivo, $usuario) {
            $pedido = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            $this->exigirEstado($pedido, [Pedido::CONFIRMADO, Pedido::ENTREGADO], 'anular');

            if ($comprobante = $pedido->comprobanteVigente()) {
                throw ValidationException::withMessages([
                    'estado' => "Tiene la {$comprobante->numero()} ({$comprobante->estado}): una venta con boleta o factura se anula con nota de crédito ante SUNAT.",
                ])->status(409);
            }

            // Primero la plata: si no alcanza el efectivo de la caja, no se toca nada.
            $this->cajas->devolverTodo($pedido, $motivo, $usuario);
            $this->devolverStock($pedido, $usuario);

            $pedido->forceFill([
                'estado' => Pedido::ANULADO,
                'anulado_at' => now(),
                'anulado_por' => $usuario->id,
                'motivo_anulacion' => $motivo,
            ])->save();

            return $pedido;
        });
    }

    /** La mercadería vuelve a los lotes de los que salió. */
    private function devolverStock(Pedido $pedido, ?User $usuario): void
    {
        $ventas = $pedido->movimientos()
            ->where('motivo', MovimientoInventario::MOTIVO_VENTA)
            ->with('lotes')
            ->get()
            ->keyBy('variante_id');

        $this->inventario->entrada(
            $pedido->sede_id,
            $pedido->items()->get()->map(fn ($i) => [
                'variante_id' => $i->variante_id,
                'cantidad' => $i->cantidad,
                // Sin costo conocido, la devolución no toca el promedio.
                'costo_unitario' => $i->costo_unitario,
                'lotes' => Inventario::lotesDe($ventas[$i->variante_id] ?? null),
            ])->all(),
            $pedido->codigo,
            null,
            $usuario,
            MovimientoInventario::MOTIVO_DEVOLUCION_VENTA,
            ['pedido_id' => $pedido->id],
        );
    }

    /**
     * @param  string[]  $permitidos
     */
    private function exigirEstado(Pedido $pedido, array $permitidos, string $accion): void
    {
        if (! in_array($pedido->estado, $permitidos, true)) {
            throw ValidationException::withMessages([
                'estado' => "No se puede {$accion} un pedido {$pedido->estado}.",
            ])->status(409);
        }
    }
}
