<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\EstadoOrden;
use App\Models\OrdenServicio;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Vale;
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

            // Vale de fidelización: su % sobre la mano de obra va como descuento del cobro.
            $vale = array_key_exists('vale_id', $datos)
                ? $this->aplicarVale($orden, $datos['vale_id'])
                : Vale::query()->where('orden_uso_id', $orden->id)->first();

            $pedido = $this->pedidos->guardar(
                $orden->pedido ?? new Pedido(['canal' => Pedido::CANAL_SERVICIO]),
                [
                    'cliente_id' => $orden->cliente_id,
                    'observacion' => "Orden de servicio {$orden->codigo}",
                    'descuento' => $vale?->descuentoSobre((float) $orden->mano_obra) ?? 0,
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

            // El adelanto entra a la caja abierta, como cualquier cobro. Sin
            // la clave `anticipo` (no vino o no cambió) no se toca.
            if (array_key_exists('anticipo', $datos)) {
                $this->guardarAnticipo($orden, $pedido, (array) $datos['anticipo'], $usuario);
            }

            return $orden->refresh();
        });
    }

    /**
     * Registra el adelanto o lo corrige. Los pagos no se editan: corregirlo
     * es devolver el anterior y cobrar el nuevo, y sólo mientras siga abierta
     * la caja del día en que entró (después el arqueo ya contó ese dinero).
     *
     * @param  array{metodo?: string|null, monto?: mixed, recibido?: mixed, referencia?: string|null}  $anticipo
     */
    private function guardarAnticipo(OrdenServicio $orden, Pedido $pedido, array $anticipo, User $usuario): void
    {
        // Bloqueada: dos "Guardar" seguidos no devuelven dos veces el mismo adelanto.
        $actualId = OrdenServicio::query()->whereKey($orden->id)->lockForUpdate()->value('anticipo_pago_id');
        $actual = $actualId ? Pago::find($actualId) : null;
        $quiere = round((float) ($anticipo['monto'] ?? 0), 2) > 0 && filled($anticipo['metodo'] ?? null);

        if (! $actual && ! $quiere) {
            return;
        }
        if ($actual && $quiere && self::mismoAnticipo($actual, $anticipo)) {
            return;
        }

        if ($actual && ! $actual->corregiblePor($usuario)) {
            $dia = $actual->created_at->copy()->setTimezone(config('app.zona_negocio'))->format('d/m');
            throw ValidationException::withMessages([
                'anticipo' => "El adelanto del {$dia} ya no se corrige: la caja de ese día se cerró. Registra un abono desde el detalle de la orden.",
            ])->status(409);
        }

        try {
            $this->reemplazarAnticipo($orden, $pedido, $actual, $quiere ? $anticipo : null, $usuario);
        } catch (ValidationException $e) {
            // Los errores de la caja (`pago.monto`, `pago.recibido`) se ven en el campo del adelanto.
            $errores = [];
            foreach ($e->errors() as $campo => $mensajes) {
                $errores[str_starts_with($campo, 'pago.') ? 'orden.anticipo.'.substr($campo, 5) : $campo] = $mensajes;
            }
            throw ValidationException::withMessages($errores)->status($e->status);
        }
    }

    /**
     * Devuelve el adelanto actual (si hay) y cobra el nuevo (si hay), en la
     * caja abierta de la sede.
     *
     * @param  array<string, mixed>|null  $nuevo
     */
    private function reemplazarAnticipo(OrdenServicio $orden, Pedido $pedido, ?Pago $actual, ?array $nuevo, User $usuario): void
    {
        if ($actual) {
            $this->cajas->devolver($pedido, [
                'metodo' => $actual->metodo,
                'monto' => (float) $actual->monto,
                'motivo' => $nuevo
                    ? "Adelanto de {$orden->codigo} corregido a S/ ".number_format((float) $nuevo['monto'], 2)
                    : "Adelanto de {$orden->codigo} quitado",
            ], $usuario);
        }

        $pago = $nuevo ? $this->cajas->cobrar($pedido, $nuevo, $usuario) : null;
        $orden->forceFill(['anticipo_pago_id' => $pago?->id])->save();
    }

    /** Mismo método, monto y n° de operación: no hay nada que corregir. */
    public static function mismoAnticipo(Pago $pago, array $anticipo): bool
    {
        return $pago->metodo === ($anticipo['metodo'] ?? null)
            && round((float) $pago->monto, 2) === round((float) ($anticipo['monto'] ?? 0), 2)
            && ($pago->referencia ?: null) === (($anticipo['referencia'] ?? null) ?: null);
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

    /**
     * Deja en la orden el vale elegido (o ninguno): libera el que tenía si
     * cambió y reserva el nuevo. Un vale es del cliente de la orden, se usa
     * una sola vez y no en la misma orden que lo generó.
     */
    private function aplicarVale(OrdenServicio $orden, ?int $valeId): ?Vale
    {
        $actual = Vale::query()->where('orden_uso_id', $orden->id)->lockForUpdate()->first();
        if ($actual && $actual->id === $valeId) {
            return $actual;
        }
        $actual?->forceFill(['orden_uso_id' => null, 'usado_at' => null])->save();

        if (! $valeId) {
            return null;
        }

        $vale = Vale::query()->whereKey($valeId)->lockForUpdate()->firstOrFail();
        $problema = match (true) {
            $vale->cliente_id !== $orden->cliente_id => 'Ese vale es de otro cliente.',
            ! $vale->disponible() => "El vale {$vale->codigo} ya se usó.",
            $vale->orden_origen_id === $orden->id => 'El vale se usa en la próxima orden, no en la que lo generó.',
            default => null,
        };
        if ($problema) {
            throw ValidationException::withMessages(['orden.vale_id' => $problema]);
        }

        $vale->forceFill(['orden_uso_id' => $orden->id, 'usado_at' => now()])->save();

        return $vale;
    }

    /**
     * Genera el vale de fidelización de la orden: un % de descuento en la mano
     * de obra de la próxima orden del cliente. Uno por orden.
     */
    public function generarVale(OrdenServicio $orden, int $porcentaje, User $usuario): Vale
    {
        return DB::transaction(function () use ($orden, $porcentaje, $usuario) {
            $orden = OrdenServicio::query()->whereKey($orden->id)->lockForUpdate()->firstOrFail();

            if ($existente = $orden->valeGenerado()->first()) {
                throw ValidationException::withMessages([
                    'vale' => "Esta orden ya dio el vale {$existente->codigo}.",
                ])->status(409);
            }

            $vale = new Vale;
            $vale->forceFill([
                'codigo' => Vale::nuevoCodigo(),
                'cliente_id' => $orden->cliente_id,
                'porcentaje' => $porcentaje,
                'orden_origen_id' => $orden->id,
                'user_id' => $usuario->id,
            ])->save();

            return $vale;
        });
    }

    /** Quita el vale que dio la orden, si el cliente todavía no lo usó. */
    public function quitarVale(OrdenServicio $orden): void
    {
        DB::transaction(function () use ($orden) {
            $vale = Vale::query()->where('orden_origen_id', $orden->id)->lockForUpdate()->firstOrFail();

            if (! $vale->disponible()) {
                throw ValidationException::withMessages([
                    'vale' => "El vale {$vale->codigo} ya se usó: no se puede quitar.",
                ])->status(409);
            }

            $vale->delete();
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

        // Reparada y entregada: ya no es basura (kilos en el dashboard).
        $orden->forceFill(['entregado_at' => now(), 'entregado_reparado' => $orden->estado === OrdenServicio::ESTADO_REPARADO]);
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
