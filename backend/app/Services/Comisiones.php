<?php

namespace App\Services;

use App\Models\ComisionPago;
use App\Models\ConceptoFinanciero;
use App\Models\OrdenServicio;
use App\Models\Pedido;
use App\Models\User;
use App\Support\Fechas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Comisiones del período: el técnico gana su % sobre la mano de obra de las
 * órdenes que entregó; el vendedor, su % sobre lo que vendió (pedidos
 * confirmados que no son de servicio técnico). Pagarla la registra como gasto.
 */
class Comisiones
{
    public const CONCEPTO = 'Comisiones';

    public function __construct(private Finanzas $finanzas) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function calcular(string $desde, string $hasta, ?int $sedeId, ?User $quien = null): array
    {
        $inicio = Fechas::inicioDelDia(Carbon::parse($desde));
        $fin = Fechas::finDelDia(Carbon::parse($hasta));

        $reparaciones = OrdenServicio::query()
            ->where('estado', OrdenServicio::ENTREGADO)
            ->whereBetween('entregado_at', [$inicio, $fin])
            ->whereNotNull('tecnico_id')
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->selectRaw('tecnico_id as user_id, COUNT(*) as cantidad, SUM(mano_obra) as base')
            ->groupBy('tecnico_id')
            ->get()
            ->keyBy('user_id');

        $ventas = Pedido::query()
            ->whereIn('estado', [Pedido::CONFIRMADO, Pedido::ENTREGADO])
            ->where('canal', '!=', Pedido::CANAL_SERVICIO)
            ->whereBetween('confirmado_at', [$inicio, $fin])
            ->whereNotNull('user_id')
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->selectRaw('user_id, COUNT(*) as cantidad, SUM(total) as base')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $pagos = ComisionPago::query()
            ->whereDate('desde', $desde)
            ->whereDate('hasta', $hasta)
            ->get()
            ->keyBy('user_id');

        // Todos los activos con porcentaje o con algo hecho en el período.
        return User::query()
            ->visiblesPara($quien)
            ->where(fn ($q) => $q->where('active', true)->orWhereIn('id', $reparaciones->keys()->merge($ventas->keys())))
            ->orderBy('name')
            ->get(['id', 'name', 'comision_reparacion', 'comision_venta'])
            ->map(function (User $u) use ($reparaciones, $ventas, $pagos) {
                $baseRep = round((float) ($reparaciones[$u->id]->base ?? 0), 2);
                $baseVen = round((float) ($ventas[$u->id]->base ?? 0), 2);
                $comRep = round($baseRep * (float) $u->comision_reparacion / 100, 2);
                $comVen = round($baseVen * (float) $u->comision_venta / 100, 2);
                $pago = $pagos[$u->id] ?? null;

                return [
                    'id' => $u->id,
                    'nombre' => $u->name,
                    'pct_reparacion' => (float) $u->comision_reparacion,
                    'pct_venta' => (float) $u->comision_venta,
                    'reparaciones' => (int) ($reparaciones[$u->id]->cantidad ?? 0),
                    'base_reparacion' => $baseRep,
                    'comision_reparacion' => $comRep,
                    'ventas' => (int) ($ventas[$u->id]->cantidad ?? 0),
                    'base_venta' => $baseVen,
                    'comision_venta' => $comVen,
                    'total' => round($comRep + $comVen, 2),
                    'pagado' => $pago ? (float) $pago->monto : null,
                    'pagado_at' => $pago?->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Paga la comisión de un empleado por el período: queda como gasto
     * "Comisiones" (si es en efectivo de la caja, sale del cajón).
     *
     * @param  array{metodo: string, de_caja?: bool, referencia?: ?string}  $pago
     */
    public function pagar(User $empleado, string $desde, string $hasta, array $pago, User $usuario): ComisionPago
    {
        return DB::transaction(function () use ($empleado, $desde, $hasta, $pago, $usuario) {
            if (ComisionPago::query()->where('user_id', $empleado->id)->whereDate('desde', $desde)->whereDate('hasta', $hasta)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['comision' => 'La comisión de este período ya se pagó.'])->status(409);
            }

            $fila = collect($this->calcular($desde, $hasta, null, $usuario))->firstWhere('id', $empleado->id);
            $monto = (float) ($fila['total'] ?? 0);
            if ($monto <= 0) {
                throw ValidationException::withMessages(['comision' => 'No hay comisión que pagar en este período.'])->status(409);
            }

            $concepto = ConceptoFinanciero::firstOrCreate(
                ['tipo' => ConceptoFinanciero::GASTO, 'nombre' => self::CONCEPTO],
                ['activo' => true],
            );
            $movimiento = $this->finanzas->registrar([
                'concepto_id' => $concepto->id,
                'monto' => $monto,
                'metodo' => $pago['metodo'],
                'fecha' => Fechas::hoyIso(),
                'descripcion' => "Comisión de {$empleado->name} del ".Carbon::parse($desde)->format('d/m/Y').' al '.Carbon::parse($hasta)->format('d/m/Y'),
                'referencia' => $pago['referencia'] ?? null,
                'de_caja' => $pago['de_caja'] ?? false,
            ], $usuario);

            $registro = new ComisionPago(['user_id' => $empleado->id, 'desde' => $desde, 'hasta' => $hasta, 'monto' => $monto]);
            $registro->forceFill(['movimiento_financiero_id' => $movimiento->id, 'pagado_por' => $usuario->id])->save();

            return $registro;
        });
    }
}
