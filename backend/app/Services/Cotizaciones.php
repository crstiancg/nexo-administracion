<?php

namespace App\Services;

use App\Models\Archivo;
use App\Models\Cotizacion;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El único lugar que cambia una cotización. No toca stock: eso pasa recién
 * cuando el pedido que nace de ella se confirma.
 */
class Cotizaciones
{
    public function __construct(private Pedidos $pedidos, private ArchivosService $archivos) {}

    /**
     * Crea o reemplaza los ítems de una cotización PENDIENTE y recalcula los
     * totales (nunca se confía en los del navegador). La foto de cada ítem va
     * en `archivos` (polimórfico), como las de un producto.
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

            // Sin FK en el polimórfico: sus fotos siguen ahí hasta el final.
            $viejos = $cotizacion->items()->get();
            $cotizacion->items()->delete();
            $subtotal = 0.0;
            foreach ($datos['items'] as $item) {
                $linea = round((float) $item['cantidad'] * (float) $item['precio_unitario'], 2);
                $subtotal += $linea;

                $libre = empty($item['variante_id']);
                $nuevo = $cotizacion->items()->create([
                    'variante_id' => $libre ? null : $item['variante_id'],
                    'componente' => ($datos['tipo'] ?? 'normal') === 'pc' ? ($item['componente'] ?? null) : null,
                    // La descripción y la unidad sólo valen en los libres.
                    'descripcion' => $libre ? $item['descripcion'] : null,
                    'unidad' => $libre ? ($item['unidad'] ?? null) : null,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $linea,
                ]);

                // Una foto que se conserva pasa al ítem nuevo (sin copiarla).
                $foto = array_values($item['archivos'] ?? [])[0] ?? null;
                if (! empty($foto['id'])) {
                    Archivo::query()->whereKey($foto['id'])->update(['archivable_id' => $nuevo->id, 'orden' => 0]);
                } elseif (($foto['archivo'] ?? null) instanceof UploadedFile) {
                    $this->archivos->sincronizar($nuevo, [['archivo' => $foto['archivo']]], "cotizaciones/{$cotizacion->id}");
                }
            }
            // Las que no pasaron a un ítem nuevo ya no las usa nadie.
            foreach ($viejos as $viejo) {
                $this->archivos->eliminarDe($viejo);
            }

            // PC armada por N equipos: los ítems son los de uno.
            $subtotal = round($subtotal, 2) * $cotizacion->multiplicador();
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
     * Los ítems libres (lo que la tienda no tiene) no pasan: no hay qué
     * descontar del stock; se consiguen y cobran aparte.
     *
     * En una PC por N equipos, cada ítem pasa N veces (la cantidad cotizada
     * es la de los N).
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
                    'estado' => 'La cotización venció el '.$cotizacion->valida_hasta->format('d/m/Y').': actualiza la validez (y los precios) antes de convertirla.',
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
        $cotizados = $cotizacion->items()->whereNotNull('variante_id')
            ->with('variante.unidad:id,fraccionable')->orderBy('id')->get()->keyBy('variante_id')
            ->each(fn ($item) => $item->cantidad = round((float) $item->cantidad * $cotizacion->multiplicador(), 3));

        if ($elegidos === null) {
            if ($cotizados->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Esta cotización sólo tiene ítems por encargo (fuera del catálogo): regístralos como producto para venderlos con un pedido.',
                ]);
            }

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
            $errores['items'] = 'Elige al menos un producto.';
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
