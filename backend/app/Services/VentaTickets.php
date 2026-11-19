<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\Sorteo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Venta de tickets sueltos de un sorteo: al precio que se elija en el
 * momento (el del sorteo es sólo el sugerido), la cantidad que sea. Es una
 * venta más (canal "sorteo"): entra a la caja con su método de pago, sale en
 * los reportes y, si se anula, sus tickets también. No regala el ticket de
 * cada compra: ya trae los comprados.
 */
class VentaTickets
{
    public function __construct(private Pedidos $pedidos, private Cajas $cajas, private Sorteos $sorteos) {}

    /**
     * @param  array{cliente_id: int, cantidad: int, precio: numeric-string|float, metodo?: string|null, recibido?: numeric-string|float|null, referencia?: string|null}  $datos
     */
    public function vender(Sorteo $sorteo, array $datos, User $usuario): Pedido
    {
        return DB::transaction(function () use ($sorteo, $datos, $usuario) {
            // Bloqueado: los números salen seguidos aunque otra venta corra a la vez.
            $sorteo = Sorteo::query()->whereKey($sorteo->id)->lockForUpdate()->firstOrFail();

            if (! $sorteo->recibeTickets()) {
                throw ValidationException::withMessages([
                    'venta' => match ($sorteo->estadoVisible()) {
                        'programado' => 'El sorteo todavía no empieza: los tickets se venden desde el '.$sorteo->inicia_el->format('d/m/Y').'.',
                        'por_sortear' => 'El período del sorteo ya terminó: no se venden más tickets.',
                        default => 'El sorteo ya no recibe tickets.',
                    },
                ])->status(409);
            }
            if (! $sorteo->participaSede($usuario->sedeOperativa())) {
                throw ValidationException::withMessages([
                    'venta' => 'Tu sede no participa en este sorteo.',
                ]);
            }

            $cantidad = (int) $datos['cantidad'];
            $pedido = $this->pedidos->guardar(new Pedido, [
                'cliente_id' => $datos['cliente_id'],
                'canal' => Pedido::CANAL_SORTEO,
                'descuento' => 0,
                'observacion' => null,
                'items' => [[
                    'variante_id' => null,
                    'descripcion' => mb_substr(($cantidad === 1 ? 'Ticket' : 'Tickets').' del sorteo '.$sorteo->nombre, 0, 250),
                    'cantidad' => $cantidad,
                    'precio_unitario' => round((float) $datos['precio'], 2),
                ]],
            ], $usuario);

            $this->pedidos->confirmar($pedido, $usuario);

            // A precio 0 son de cortesía: no hay nada que cobrar.
            if ((float) $pedido->total > 0) {
                $this->comoVenta(fn () => $this->cajas->cobrar($pedido, [
                    'metodo' => $datos['metodo'],
                    'monto' => $pedido->total,
                    'recibido' => $datos['recibido'] ?? null,
                    'referencia' => $datos['referencia'] ?? null,
                ], $usuario));
            }

            $this->pedidos->entregar($pedido);
            $this->sorteos->crearTickets($sorteo, $pedido->refresh(), $cantidad, true);

            return $pedido;
        });
    }

    /** Los errores del cobro (pago.recibido, caja…) con las claves de este formulario. */
    private function comoVenta(callable $accion): void
    {
        try {
            $accion();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn ($mensajes, $clave) => [str_replace('pago.', 'venta.', $clave) => $mensajes])
                ->all())->status($e->status);
        }
    }
}
