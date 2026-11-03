<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use App\Support\Fechas;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Caja, pagos y arqueo. El único lugar que registra dinero:
 *
 * - Cada sede tiene su caja: el dinero entra a la caja abierta de la sede
 *   del usuario que cobra (es donde está físicamente el cajón).
 * - Todo pago (de cualquier método) entra a la caja abierta: así el cierre
 *   muestra lo cobrado del día por método. El arqueo compara sólo efectivo.
 * - Pagos y movimientos son inmutables: un error se corrige con otro
 *   registro (una devolución), nunca editando.
 * - La caja es diaria: la de un día anterior se cierra sola a medianoche
 *   de su día (sin arqueo) la próxima vez que alguien la consulta.
 * - La caja abierta se bloquea (FOR UPDATE) al registrar: un pago no puede
 *   colarse en una caja que se está cerrando en ese mismo instante.
 */
class Cajas
{
    /**
     * La caja abierta de la sede. Si es de un día anterior se cierra sola
     * (a medianoche de su día) y no hay caja abierta: la de hoy se abre en
     * el punto de venta.
     */
    public function actual(int $sedeId): ?Caja
    {
        $caja = Caja::query()->where('sede_id', $sedeId)->where('estado', Caja::ABIERTA)->first();

        if ($caja?->esDeOtroDia()) {
            $this->cerrarAutomatico($caja);

            return null;
        }

        return $caja;
    }

    /**
     * Cierre a medianoche de una caja que nadie cerró. Sin arqueo: nadie
     * contó el efectivo, así que contado y diferencia quedan vacíos (no se
     * inventa un "cuadra exacto") y la observación lo dice.
     */
    public function cerrarAutomatico(Caja $caja): void
    {
        DB::transaction(function () use ($caja) {
            $caja = Caja::query()->whereKey($caja->id)->lockForUpdate()->first();
            if (! $caja?->estaAbierta()) {
                return;
            }

            $medianoche = Fechas::finDelDia($caja->abierta_at->copy()->setTimezone(Fechas::zona()));

            $caja->forceFill([
                'estado' => Caja::CERRADA,
                'abierta' => null,
                'monto_esperado' => $this->resumen($caja)['efectivo_esperado'],
                'monto_contado' => null,
                'diferencia' => null,
                'observacion_cierre' => 'Cierre automático a medianoche: sin arqueo (nadie contó el efectivo).',
                'cerrada_por' => null,
                'cerrada_at' => $medianoche,
            ])->save();
        });
    }

    public function abrir(int $sedeId, float $montoApertura, ?User $usuario): Caja
    {
        try {
            return DB::transaction(fn () => Caja::query()->forceCreate([
                'sede_id' => $sedeId,
                'estado' => Caja::ABIERTA,
                'abierta' => true,
                'monto_apertura' => $montoApertura,
                'abierta_por' => $usuario?->id,
                'abierta_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // El unique de `abierta` ganó la carrera: ya hay una abierta.
            throw $this->conflicto('caja', 'Ya hay una caja abierta en esta sede.');
        }
    }

    /**
     * Totales de la caja: cobros por método, ingresos/egresos y el efectivo
     * que DEBERÍA haber en el cajón.
     *
     * @return array<string, mixed>
     */
    public function resumen(Caja $caja): array
    {
        $porMetodo = $caja->pagos()
            ->selectRaw('metodo, SUM(monto) as total, COUNT(*) as cantidad')
            ->groupBy('metodo')
            ->get()
            ->keyBy('metodo');

        $cobros = collect(Pago::METODOS)->map(fn ($label, $metodo) => [
            'metodo' => $metodo,
            'label' => $label,
            'total' => round((float) ($porMetodo[$metodo]->total ?? 0), 2),
            'cantidad' => (int) ($porMetodo[$metodo]->cantidad ?? 0),
        ])->values();

        $ingresos = (float) $caja->movimientos()->where('tipo', MovimientoCaja::INGRESO)->sum('monto');
        $egresos = (float) $caja->movimientos()->where('tipo', MovimientoCaja::EGRESO)->sum('monto');
        $efectivo = (float) ($porMetodo[Pago::EFECTIVO]->total ?? 0);

        return [
            'monto_apertura' => round((float) $caja->monto_apertura, 2),
            'cobros' => $cobros,
            'total_cobrado' => round($cobros->sum('total'), 2),
            'ingresos' => round($ingresos, 2),
            'egresos' => round($egresos, 2),
            'efectivo_esperado' => round((float) $caja->monto_apertura + $efectivo + $ingresos - $egresos, 2),
        ];
    }

    /**
     * Ventas del turno: los pedidos de la sede confirmados mientras la caja
     * estuvo abierta, con su costo congelado y la ganancia. Se calcula sobre
     * lo VENDIDO y no sobre lo cobrado (un adelanto entra a la caja sin ser
     * venta todavía; una devolución sale aunque la venta fuera de otro día).
     *
     * @return array<string, mixed>
     */
    public function ventasDelTurno(Caja $caja): array
    {
        $pedidos = Pedido::query()
            ->where('sede_id', $caja->sede_id)
            ->whereIn('estado', [Pedido::CONFIRMADO, Pedido::ENTREGADO])
            ->whereBetween('confirmado_at', [$caja->abierta_at, $caja->cerrada_at ?? now()]);

        $totales = (clone $pedidos)->selectRaw('COUNT(*) as ventas, COALESCE(SUM(total), 0) as total, COALESCE(SUM(igv), 0) as igv')->first();

        $costos = DB::table('pedido_items')
            ->whereIn('pedido_id', (clone $pedidos)->select('id'))
            ->selectRaw('COALESCE(SUM(cantidad * costo_unitario), 0) as costo')
            ->selectRaw('SUM(CASE WHEN costo_unitario IS NULL THEN 1 ELSE 0 END) as sin_costo')
            ->first();

        $total = round((float) $totales->total, 2);
        $costo = round((float) $costos->costo, 2);

        return [
            'ventas' => (int) $totales->ventas,
            'total' => $total,
            'igv' => round((float) $totales->igv, 2),
            'costo' => $costo,
            'ganancia' => round($total - $costo, 2),
            'margen' => $total > 0 ? round(($total - $costo) / $total * 100, 1) : null,
            // Ítems sin costo (entraron por un ajuste): la ganancia no los descuenta.
            'items_sin_costo' => (int) $costos->sin_costo,
        ];
    }

    /**
     * Cobro de un pedido pendiente o confirmado (un pendiente puede recibir
     * un adelanto). Nunca más que el saldo. En efectivo, `recibido` es lo que
     * entregó el cliente y se calcula el vuelto.
     *
     * @param  array{metodo: string, monto: numeric-string|float, recibido?: numeric-string|float|null, referencia?: string|null}  $datos
     */
    public function cobrar(Pedido $pedido, array $datos, ?User $usuario): Pago
    {
        return DB::transaction(function () use ($pedido, $datos, $usuario) {
            $caja = $this->cajaAbiertaBloqueada($usuario);
            $pedido = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->firstOrFail();

            if (! in_array($pedido->estado, [Pedido::PENDIENTE, Pedido::CONFIRMADO], true)) {
                throw $this->conflicto('pedido', "No se cobra un pedido {$pedido->estado}.");
            }

            $monto = round((float) $datos['monto'], 2);
            $saldo = $pedido->saldo();
            if ($monto > $saldo) {
                throw ValidationException::withMessages([
                    'pago.monto' => 'El monto supera el saldo del pedido ('.$this->soles($saldo).').',
                ]);
            }

            $recibido = null;
            $vuelto = null;
            if ($datos['metodo'] === Pago::EFECTIVO) {
                $recibido = round((float) ($datos['recibido'] ?? $monto), 2);
                if ($recibido < $monto) {
                    throw ValidationException::withMessages([
                        'pago.recibido' => 'Lo recibido no alcanza para el monto a cobrar.',
                    ]);
                }
                $vuelto = round($recibido - $monto, 2);
            }

            return Pago::create([
                'pedido_id' => $pedido->id,
                'caja_id' => $caja->id,
                'metodo' => $datos['metodo'],
                'monto' => $monto,
                'recibido' => $recibido,
                'vuelto' => $vuelto,
                'referencia' => $datos['referencia'] ?? null,
                'user_id' => $usuario?->id,
            ]);
        });
    }

    /**
     * Devuelve dinero de un pedido (un cobro mal cargado, o antes de cancelar
     * uno pagado). Queda como pago NEGATIVO en la caja abierta: el historial
     * no se reescribe. Nunca más que lo pagado; en efectivo, nunca más que lo
     * que hay en el cajón.
     *
     * @param  array{metodo: string, monto: numeric-string|float, motivo: string}  $datos
     */
    public function devolver(Pedido $pedido, array $datos, ?User $usuario): Pago
    {
        return DB::transaction(function () use ($pedido, $datos, $usuario) {
            $caja = $this->cajaAbiertaBloqueada($usuario);
            $pedido = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->firstOrFail();

            if ($pedido->estado === Pedido::ENTREGADO) {
                throw $this->conflicto('pedido', 'Un pedido entregado no se devuelve desde acá.');
            }

            $monto = round((float) $datos['monto'], 2);
            $pagado = $pedido->pagado();
            if ($monto > $pagado) {
                throw ValidationException::withMessages([
                    'pago.monto' => 'No se puede devolver más de lo pagado ('.$this->soles($pagado).').',
                ]);
            }

            if ($datos['metodo'] === Pago::EFECTIVO) {
                $this->exigirEfectivo($caja, $monto, 'pago.monto');
            }

            return Pago::create([
                'pedido_id' => $pedido->id,
                'caja_id' => $caja->id,
                'metodo' => $datos['metodo'],
                'monto' => -$monto,
                'motivo' => $datos['motivo'],
                'user_id' => $usuario?->id,
            ]);
        });
    }

    /**
     * Devuelve TODO lo cobrado de un pedido (al anular la venta), por el
     * mismo método con que se pagó, desde la caja abierta de hoy. Se llama
     * dentro de la transacción de Pedidos::anular().
     *
     * @return array<int, Pago>  las devoluciones registradas
     */
    public function devolverTodo(Pedido $pedido, string $motivo, ?User $usuario): array
    {
        $porMetodo = $pedido->pagos()
            ->selectRaw('metodo, SUM(monto) as neto')
            ->groupBy('metodo')
            ->pluck('neto', 'metodo')
            ->map(fn ($neto) => round((float) $neto, 2))
            ->filter(fn ($neto) => $neto > 0);

        if ($porMetodo->isEmpty()) {
            return [];
        }

        $caja = $this->cajaAbiertaBloqueada($usuario);
        if ($porMetodo->has(Pago::EFECTIVO)) {
            $this->exigirEfectivo($caja, $porMetodo[Pago::EFECTIVO], 'pedido');
        }

        return $porMetodo->map(fn ($neto, $metodo) => Pago::create([
            'pedido_id' => $pedido->id,
            'caja_id' => $caja->id,
            'metodo' => $metodo,
            'monto' => -$neto,
            'motivo' => mb_substr("Venta anulada: {$motivo}", 0, 200),
            'user_id' => $usuario?->id,
        ]))->values()->all();
    }

    public function movimiento(string $tipo, float $monto, string $concepto, ?User $usuario): MovimientoCaja
    {
        return DB::transaction(function () use ($tipo, $monto, $concepto, $usuario) {
            $caja = $this->cajaAbiertaBloqueada($usuario);

            if ($tipo === MovimientoCaja::EGRESO) {
                $this->exigirEfectivo($caja, $monto, 'movimiento.monto');
            }

            return MovimientoCaja::create([
                'caja_id' => $caja->id,
                'tipo' => $tipo,
                'monto' => round($monto, 2),
                'concepto' => $concepto,
                'user_id' => $usuario?->id,
            ]);
        });
    }

    /**
     * Arqueo: el efectivo contado contra el esperado. La diferencia queda
     * registrada (faltante negativo, sobrante positivo); no se "corrige".
     */
    public function cerrar(Caja $caja, float $montoContado, ?string $observacion, ?User $usuario): Caja
    {
        return DB::transaction(function () use ($caja, $montoContado, $observacion, $usuario) {
            $caja = Caja::query()->whereKey($caja->id)->lockForUpdate()->firstOrFail();
            if (! $caja->estaAbierta()) {
                throw $this->conflicto('caja', 'Esta caja ya está cerrada.');
            }

            $esperado = $this->resumen($caja)['efectivo_esperado'];

            // Una diferencia sin explicación queda para siempre en el
            // historial sin que nadie sepa qué pasó.
            if (round($montoContado - $esperado, 2) != 0 && blank($observacion)) {
                throw ValidationException::withMessages([
                    'caja.observacion' => 'Con diferencia, anotá qué pasó antes de cerrar.',
                ]);
            }

            $caja->forceFill([
                'estado' => Caja::CERRADA,
                'abierta' => null,
                'monto_esperado' => $esperado,
                'monto_contado' => round($montoContado, 2),
                'diferencia' => round($montoContado - $esperado, 2),
                'observacion_cierre' => $observacion,
                'cerrada_por' => $usuario?->id,
                'cerrada_at' => now(),
            ])->save();

            return $caja;
        });
    }

    private function cajaAbiertaBloqueada(?User $usuario): Caja
    {
        $sedeId = $usuario?->sedeOperativa();
        $caja = Caja::query()->where('sede_id', $sedeId)->where('estado', Caja::ABIERTA)->lockForUpdate()->first();

        if (! $caja) {
            throw $this->conflicto('caja', 'No hay una caja abierta en tu sede: abrí la caja para registrar dinero.');
        }

        // La caja es diaria: la de ayer se cierra (arqueo) antes de cobrar hoy.
        if ($caja->esDeOtroDia()) {
            $dia = $caja->abierta_at->copy()->setTimezone(config('app.zona_negocio'))->format('d/m');
            // No se cierra acá: el error deshace la transacción. La cierra la
            // próxima consulta de la caja (actual()), que hace el POS al recibir esto.
            throw $this->conflicto('caja', "La caja del {$dia} ya no cobra: se cerró a medianoche. Abrí la de hoy para seguir.");
        }

        return $caja;
    }

    private function exigirEfectivo(Caja $caja, float $monto, string $campo): void
    {
        $disponible = $this->resumen($caja)['efectivo_esperado'];

        if ($monto > $disponible) {
            throw ValidationException::withMessages([
                $campo => 'No hay suficiente efectivo en caja ('.$this->soles($disponible).').',
            ]);
        }
    }

    private function conflicto(string $campo, string $mensaje): ValidationException
    {
        return ValidationException::withMessages([$campo => $mensaje])->status(409);
    }

    private function soles(float $monto): string
    {
        return 'S/ '.number_format($monto, 2, '.', ',');
    }
}
