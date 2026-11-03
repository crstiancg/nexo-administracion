<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El único lugar que cambia una cotización. No toca stock: eso pasa recién
 * cuando el pedido que nace de ella se confirma.
 */
class Cotizaciones
{
    public function __construct(private Pedidos $pedidos) {}

    /**
     * Crea o reemplaza los ítems de una cotización PENDIENTE y recalcula los
     * totales (nunca se confía en los del navegador).
     *
     * @param  array<string, mixed>  $datos  cotización validada (con `items`)
     */
    public function guardar(Cotizacion $cotizacion, array $datos, User $usuario): Cotizacion
    {
        return DB::transaction(function () use ($cotizacion, $datos, $usuario) {
            $nueva = ! $cotizacion->exists;

            $cotizacion->fill($datos);
            if ($nueva) {
                $cotizacion->forceFill([
                    'estado' => Cotizacion::PENDIENTE,
                    'sede_id' => $usuario->sedeOperativa(),
                    'user_id' => $usuario->id,
                    'subtotal' => 0,
                    'total' => 0,
                ]);
            }
            $cotizacion->save();

            if ($nueva) {
                $cotizacion->forceFill(['codigo' => 'COT-'.str_pad((string) $cotizacion->id, 6, '0', STR_PAD_LEFT)])->save();
            }

            $cotizacion->items()->delete();
            $subtotal = 0.0;
            foreach ($datos['items'] as $item) {
                $linea = round((float) $item['cantidad'] * (float) $item['precio_unitario'], 2);
                $subtotal += $linea;

                $cotizacion->items()->create([
                    'variante_id' => $item['variante_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $linea,
                ]);
            }

            $cotizacion->forceFill([
                'subtotal' => round($subtotal, 2),
                'total' => round($subtotal - (float) ($datos['descuento'] ?? 0), 2),
            ])->save();

            return $cotizacion;
        });
    }

    /**
     * El cliente aceptó: nace un pedido PENDIENTE con los precios cotizados
     * (se confirma, cobra y entrega por el flujo de pedidos).
     *
     * `$elegidos` ([{variante_id, cantidad}]): lo que de verdad pide, si no
     * es todo. Sólo ítems de la cotización y sin pasar la cantidad cotizada;
     * el descuento se reparte en proporción a lo que queda. null = todo.
     *
     * @param  array<int, array{variante_id: int, cantidad: float|string}>|null  $elegidos
     */
    public function convertir(Cotizacion $cotizacion, User $usuario, ?array $elegidos = null): Pedido
    {
        return DB::transaction(function () use ($cotizacion, $usuario, $elegidos) {
            $cotizacion = Cotizacion::query()->whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();
            $this->exigirPendiente($cotizacion, 'convertir');

            if ($cotizacion->vencida()) {
                throw ValidationException::withMessages([
                    'estado' => 'La cotización venció el '.$cotizacion->valida_hasta->format('d/m/Y').': actualizá la validez (y los precios) antes de convertirla.',
                ])->status(409);
            }

            $items = $this->itemsElegidos($cotizacion, $elegidos);
            $subtotal = collect($items)->sum(fn ($i) => round((float) $i['cantidad'] * (float) $i['precio_unitario'], 2));
            $descuento = (float) $cotizacion->subtotal > 0
                ? min($subtotal, round((float) $cotizacion->descuento * $subtotal / (float) $cotizacion->subtotal, 2))
                : 0.0;

            $pedido = $this->pedidos->guardar(new Pedido, [
                'cliente_id' => $cotizacion->cliente_id,
                'canal' => 'cotizacion',
                'descuento' => $descuento,
                'observacion' => mb_substr("Cotización {$cotizacion->codigo}".($cotizacion->condiciones ? " · {$cotizacion->condiciones}" : ''), 0, 500),
                'items' => $items,
            ], $usuario);

            $cotizacion->forceFill(['estado' => Cotizacion::CONVERTIDA, 'pedido_id' => $pedido->id])->save();

            return $pedido;
        });
    }

    /**
     * Las líneas del pedido: todas las de la cotización o sólo las elegidas
     * (con su cantidad, nunca mayor a la cotizada), al precio cotizado.
     *
     * @return array<int, array{variante_id: int, cantidad: float, precio_unitario: string}>
     */
    private function itemsElegidos(Cotizacion $cotizacion, ?array $elegidos): array
    {
        $cotizados = $cotizacion->items()->with('variante.unidad:id,fraccionable')->orderBy('id')->get()->keyBy('variante_id');

        if ($elegidos === null) {
            return $cotizados->map(fn ($i) => [
                'variante_id' => $i->variante_id,
                'cantidad' => $i->cantidad,
                'precio_unitario' => $i->precio_unitario,
            ])->values()->all();
        }

        $errores = [];
        $items = [];
        foreach (array_values($elegidos) as $n => $elegido) {
            $cotizado = $cotizados->get((int) $elegido['variante_id']);
            if (! $cotizado) {
                $errores["items.{$n}.variante_id"] = 'Ese producto no está en la cotización.';

                continue;
            }
            if ((float) $elegido['cantidad'] - (float) $cotizado->cantidad > 0.0005) {
                $errores["items.{$n}.cantidad"] = 'No puede ser más de lo cotizado ('.Inventario::formatear((float) $cotizado->cantidad).').';

                continue;
            }
            if (! $cotizado->variante->unidad?->fraccionable && floor((float) $elegido['cantidad']) != (float) $elegido['cantidad']) {
                $errores["items.{$n}.cantidad"] = 'Esta presentación se vende por unidad entera.';

                continue;
            }
            $items[] = [
                'variante_id' => $cotizado->variante_id,
                'cantidad' => round((float) $elegido['cantidad'], 3),
                'precio_unitario' => $cotizado->precio_unitario,
            ];
        }

        if (! $items && ! $errores) {
            $errores['items'] = 'Elegí al menos un producto.';
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return $items;
    }

    public function rechazar(Cotizacion $cotizacion): Cotizacion
    {
        return DB::transaction(function () use ($cotizacion) {
            $cotizacion = Cotizacion::query()->whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();
            $this->exigirPendiente($cotizacion, 'rechazar');

            $cotizacion->forceFill(['estado' => Cotizacion::RECHAZADA])->save();

            return $cotizacion;
        });
    }

    private function exigirPendiente(Cotizacion $cotizacion, string $accion): void
    {
        if ($cotizacion->estado !== Cotizacion::PENDIENTE) {
            throw ValidationException::withMessages([
                'estado' => "No se puede {$accion} una cotización {$cotizacion->estado}.",
            ])->status(409);
        }
    }
}
