<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\EstadoOrden;
use App\Models\OrdenServicio;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * El único lugar que crea, edita y cambia de estado una orden de servicio.
 *
 * El cobro de la orden es un pedido (canal "servicio") que se arma acá: los
 * repuestos son líneas con presentación y la mano de obra una línea de
 * servicio. El pedido queda PENDIENTE mientras el equipo está en el taller
 * (recibe abonos por App\Services\Cajas) y al entregar se confirma —descuenta
 * los repuestos del stock— y se entrega, con el saldo en cero.
 */
class OrdenesServicio
{
    /** Poner a otro usuario como técnico a cargo (nombre de ruta, ver routes/api.php). */
    public const PERMISO_ASIGNAR_TECNICO = 'ordenes-servicio.asignar-tecnico';

    public function __construct(private Pedidos $pedidos, private Cajas $cajas) {}

    /**
     * Crea o edita una orden (no entregada) y rearma su pedido de cobro.
     *
     * @param  array<string, mixed>  $datos  orden validada (con `repuestos` y, al crear, `anticipo`)
     */
    public function guardar(OrdenServicio $orden, array $datos, User $usuario): OrdenServicio
    {
        return DB::transaction(function () use ($orden, $datos, $usuario) {
            $nueva = ! $orden->exists;

            if (! $nueva && ! $orden->editable()) {
                throw ValidationException::withMessages([
                    'estado' => 'Una orden entregada ya no se edita.',
                ])->status(409);
            }

            // Sin clave no se guarda el valor (un "Sin bloqueo" con PIN viejo).
            if (($datos['seguridad_tipo'] ?? 'ninguno') === 'ninguno') {
                $datos['seguridad_valor'] = null;
            }

            // El técnico a cargo es quien registra; elegir a otro (al crear o
            // al editar) pide el permiso en el rol, no el implícito de la ruta.
            if (! $usuario->checkPermissionTo(self::PERMISO_ASIGNAR_TECNICO)) {
                if ($nueva) {
                    $datos['tecnico_id'] = $usuario->id;
                } else {
                    unset($datos['tecnico_id']);
                }
            }

            $orden->fill($datos);
            if ($nueva) {
                $orden->forceFill([
                    'estado' => EstadoOrden::inicial(),
                    'sede_id' => $usuario->sedeOperativa(),
                    'user_id' => $usuario->id,
                    'token' => Str::random(32),
                    'recibido_at' => now(),
                ]);
            }
            $orden->save();

            if ($nueva) {
                $orden->forceFill(['codigo' => 'OS-'.str_pad((string) $orden->id, 6, '0', STR_PAD_LEFT)])->save();
                $orden->movimientos()->create([
                    'estado' => $orden->estado,
                    'nota' => 'Equipo recibido.',
                    'user_id' => $usuario->id,
                ]);
            }

            $pedido = $this->pedidos->guardar(
                $orden->pedido ?? new Pedido(['canal' => Pedido::CANAL_SERVICIO]),
                [
                    'cliente_id' => $orden->cliente_id,
                    'observacion' => "Orden de servicio {$orden->codigo}",
                    'descuento' => 0,
                    'items' => $this->lineasDeCobro($orden, $datos['repuestos'] ?? []),
                ],
                $usuario,
            );
            if ($nueva) {
                $orden->forceFill(['pedido_id' => $pedido->id])->save();
            }

            // Nace de una cotización aceptada: queda convertida en esta orden.
            if ($nueva && ! empty($datos['cotizacion_id'])) {
                $cotizacion = Cotizacion::query()->whereKey($datos['cotizacion_id'])->lockForUpdate()->firstOrFail();
                if ($cotizacion->estado !== Cotizacion::PENDIENTE) {
                    throw ValidationException::withMessages([
                        'cotizacion_id' => "La cotización {$cotizacion->codigo} ya está {$cotizacion->estado}.",
                    ])->status(409);
                }
                $cotizacion->forceFill(['estado' => Cotizacion::CONVERTIDA, 'orden_servicio_id' => $orden->id])->save();
            }

            // El adelanto entra a la caja abierta, como cualquier cobro.
            if ($nueva && (float) ($datos['anticipo']['monto'] ?? 0) > 0) {
                $this->cajas->cobrar($pedido, $datos['anticipo'], $usuario);
            }

            return $orden->refresh();
        });
    }

    /**
     * Cambia el estado y lo deja en el historial. Entregar confirma el pedido
     * (salen los repuestos del stock) y exige el saldo en cero.
     */
    public function cambiarEstado(OrdenServicio $orden, string $estado, ?string $nota, User $usuario): OrdenServicio
    {
        return DB::transaction(function () use ($orden, $estado, $nota, $usuario) {
            // Bloqueada: dos clics en "Entregar" no confirman dos veces.
            $orden = OrdenServicio::query()->whereKey($orden->id)->lockForUpdate()->firstOrFail();

            if ($orden->estado === EstadoOrden::entregado()) {
                throw ValidationException::withMessages([
                    'estado' => 'La orden ya fue entregada.',
                ])->status(409);
            }
            if ($orden->estado === $estado) {
                throw ValidationException::withMessages([
                    'estado' => 'La orden ya está en ese estado.',
                ]);
            }
            if (! EstadoOrden::deClave($estado)?->activo) {
                throw ValidationException::withMessages([
                    'estado' => 'Ese estado no existe o está desactivado.',
                ]);
            }

            if ($estado === EstadoOrden::entregado()) {
                $this->entregar($orden, $usuario);
            }

            $orden->forceFill(['estado' => $estado])->save();
            $orden->movimientos()->create([
                'estado' => $estado,
                'nota' => $nota,
                'user_id' => $usuario->id,
            ]);

            return $orden;
        });
    }

    private function entregar(OrdenServicio $orden, User $usuario): void
    {
        if (! in_array($orden->estado, EstadoOrden::entregables(), true)) {
            $listos = EstadoOrden::todos()->whereIn('clave', EstadoOrden::entregables())->where('activo', true)->pluck('nombre');
            throw ValidationException::withMessages([
                'estado' => 'Sólo se entrega desde: '.$listos->implode(', ').'.',
            ])->status(409);
        }

        $pedido = $orden->pedido;
        if ($pedido->estado === Pedido::PENDIENTE) {
            $pedido = $this->pedidos->confirmar($pedido, $usuario);
        }
        // Falta cobrar → 409 con el saldo; la transacción deshace la confirmación.
        $this->pedidos->entregar($pedido);

        $orden->forceFill(['entregado_at' => now()]);
    }

    /**
     * Repuestos primero y la mano de obra al final: si un repuesto no tiene
     * stock al entregar, el error del pedido apunta al índice correcto.
     *
     * @param  array<int, array<string, mixed>>  $repuestos
     * @return array<int, array<string, mixed>>
     */
    private function lineasDeCobro(OrdenServicio $orden, array $repuestos): array
    {
        $lineas = array_map(fn (array $r) => [
            'variante_id' => $r['variante_id'],
            'cantidad' => $r['cantidad'],
            'precio_unitario' => $r['precio_unitario'],
        ], array_values($repuestos));

        if ((float) $orden->mano_obra > 0) {
            $orden->loadMissing(['tipoEquipo:id,nombre', 'marca:id,nombre']);
            $lineas[] = [
                'variante_id' => null,
                'descripcion' => mb_substr("Servicio técnico {$orden->codigo}: ".$orden->equipo(), 0, 250),
                'cantidad' => 1,
                'precio_unitario' => $orden->mano_obra,
            ];
        }

        return $lineas;
    }
}
