<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Pedido;
use App\Models\User;
use App\Support\Fechas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Otorga créditos: el saldo de un pedido (una venta o el cobro de una orden
 * de servicio) en cuotas. Con crédito el pedido se puede entregar con saldo
 * y se sigue cobrando después de entregado (Pedidos::entregar, Cajas::cobrar).
 */
class Creditos
{
    /**
     * @param  array{frecuencia: string, numero_cuotas: int, primer_vencimiento: string, observacion?: ?string}  $datos
     */
    public function otorgar(Pedido $pedido, array $datos, User $usuario): Credito
    {
        return DB::transaction(function () use ($pedido, $datos, $usuario) {
            $pedido = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->firstOrFail();

            if ($pedido->credito()->exists()) {
                $this->falla('Este pedido ya tiene un crédito.');
            }
            if (! in_array($pedido->estado, [Pedido::PENDIENTE, Pedido::CONFIRMADO], true)) {
                $this->falla("No se da crédito a un pedido {$pedido->estado}.");
            }
            if (! $pedido->cliente_id) {
                $this->falla('Para dar crédito el pedido tiene que tener un cliente identificado.');
            }

            $pagado = $pedido->pagado();
            $saldo = round((float) $pedido->total - $pagado, 2);
            if ($saldo <= 0) {
                $this->falla('El pedido no tiene saldo para financiar.');
            }

            $credito = new Credito($datos);
            $credito->forceFill([
                'pedido_id' => $pedido->id,
                'cliente_id' => $pedido->cliente_id,
                'sede_id' => $pedido->sede_id,
                'monto' => $saldo,
                'pagado_inicial' => $pagado,
                'user_id' => $usuario->id,
            ])->save();

            // Cuotas iguales; la última absorbe los centavos que sobran.
            $n = (int) $datos['numero_cuotas'];
            $base = floor($saldo / $n * 100) / 100;
            $vence = Carbon::parse($datos['primer_vencimiento']);
            for ($i = 1; $i <= $n; $i++) {
                $credito->cuotas()->create([
                    'numero' => $i,
                    'vence' => $vence->toDateString(),
                    'monto' => $i === $n ? round($saldo - $base * ($n - 1), 2) : $base,
                ]);
                $vence = $this->siguiente($vence, $datos['frecuencia']);
            }

            return $credito->load('cuotas');
        });
    }

    private function siguiente(Carbon $fecha, string $frecuencia): Carbon
    {
        return match ($frecuencia) {
            'semanal' => $fecha->copy()->addWeek(),
            'quincenal' => $fecha->copy()->addDays(15),
            default => $fecha->copy()->addMonthNoOverflow(),
        };
    }

    /** Créditos con alguna cuota vencida sin cubrir (para la campana). */
    public function conCuotasVencidas(int $sedeId): int
    {
        return Credito::query()
            ->where('sede_id', $sedeId)
            ->whereHas('cuotas', fn ($q) => $q->whereDate('vence', '<', Fechas::hoy()))
            ->with(['cuotas', 'pedido'])
            ->get()
            ->filter(fn (Credito $c) => collect($c->estadoCuotas())->contains('estado', 'vencida'))
            ->count();
    }

    private function falla(string $mensaje): never
    {
        throw ValidationException::withMessages(['credito' => $mensaje])->status(409);
    }
}
