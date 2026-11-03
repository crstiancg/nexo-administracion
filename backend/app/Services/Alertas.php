<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\Lote;
use App\Models\Pedido;
use App\Models\Stock;
use App\Models\User;
use App\Support\Fechas;
use App\Support\Permisos;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que necesita atención en la sede del usuario: la campana del menú y el
 * dashboard. Cada alerta aparece sólo si el usuario puede entrar a la
 * pantalla que la resuelve (no tiene sentido avisar lo que no puede hacer).
 *
 * `nivel`: critical (bloquea algo: no se puede vender) | warning (hay que
 * hacerlo pronto) | info (pendiente de rutina).
 */
class Alertas
{
    public function __construct(private Cajas $cajas) {}

    /**
     * @return list<array{clave: string, nivel: string, titulo: string, detalle: string, cantidad: int, to: string}>
     */
    public function para(User $user): array
    {
        $sedeId = $user->sedeOperativa();
        if (! $sedeId) {
            return [];
        }

        $alertas = [];
        $puede = fn (string $ruta) => Permisos::puede($user, $ruta);

        // ── Caja ──
        if ($puede('cajas.actual')) {
            $caja = $this->cajas->actual($sedeId);
            if ($caja?->esDeOtroDia()) {
                $dia = $caja->abierta_at->copy()->setTimezone(Fechas::zona())->format('d/m');
                $alertas[] = $this->alerta('caja_vencida', 'critical', "La caja del {$dia} sigue abierta",
                    'No se puede cobrar hasta cerrarla (arqueo) desde el punto de venta.', 1, '/pos');
            } elseif ($caja && now(Fechas::zona())->hour >= (int) config('app.hora_aviso_cierre')) {
                $alertas[] = $this->alerta('caja_cierre', 'warning', 'Fin del día: cerrá la caja',
                    'A medianoche se cierra sola, pero sin arqueo: hacé el cierre contando el efectivo.', 1, '/pos');
            }
        }

        // ── Lotes ──
        if ($puede('inventario.lotes')) {
            $lotes = Lote::query()->where('sede_id', $sedeId)->where('cantidad', '>', 0);
            $vencidos = (clone $lotes)->whereDate('vence_at', '<', Fechas::hoy())->count();
            $porVencer = (clone $lotes)
                ->whereDate('vence_at', '>=', Fechas::hoy())
                ->whereDate('vence_at', '<=', Fechas::hoy()->addDays(Lote::DIAS_POR_VENCER))
                ->count();

            if ($vencidos) {
                $alertas[] = $this->alerta('lotes_vencidos', 'critical', $this->plural($vencidos, 'lote vencido', 'lotes vencidos'),
                    'Siguen en el stock pero no se venden: sacalos como merma.', $vencidos, '/vencimientos');
            }
            if ($porVencer) {
                $alertas[] = $this->alerta('lotes_por_vencer', 'warning', $this->plural($porVencer, 'lote por vencer', 'lotes por vencer'),
                    'Vencen en los próximos '.Lote::DIAS_POR_VENCER.' días.', $porVencer, '/vencimientos');
            }
        }

        // ── Stock bajo el mínimo ──
        if ($puede('inventario.index')) {
            $bajos = $this->bajoMinimo($sedeId);
            if ($bajos) {
                $alertas[] = $this->alerta('bajo_minimo', 'warning', $this->plural($bajos, 'presentación bajo el mínimo', 'presentaciones bajo el mínimo'),
                    'Hay que reponer para no quedarse sin stock.', $bajos, '/reponer');
            }
        }

        // ── Pedidos ──
        if ($puede('pedidos.index')) {
            $pendientes = Pedido::query()->where('sede_id', $sedeId)->where('estado', Pedido::PENDIENTE)->count();
            if ($pendientes) {
                $alertas[] = $this->alerta('pedidos_pendientes', 'info', $this->plural($pendientes, 'pedido pendiente', 'pedidos pendientes'),
                    'Sin confirmar: todavía no descontaron stock.', $pendientes, '/pedidos');
            }
        }

        // ── Cotizaciones que vencen pronto ──
        if ($puede('cotizaciones.index')) {
            $porVencer = Cotizacion::query()
                ->where('sede_id', $sedeId)
                ->where('estado', Cotizacion::PENDIENTE)
                ->whereDate('valida_hasta', '>=', Fechas::hoy())
                ->whereDate('valida_hasta', '<=', Fechas::hoy()->addDays(2))
                ->count();
            if ($porVencer) {
                $alertas[] = $this->alerta('cotizaciones_por_vencer', 'info', $this->plural($porVencer, 'cotización vence pronto', 'cotizaciones vencen pronto'),
                    'Vencen en los próximos 2 días: buen momento para llamar al cliente.', $porVencer, '/cotizaciones');
            }
        }

        return $alertas;
    }

    /**
     * Presentaciones que la sede vende y están por debajo de su mínimo.
     */
    public function bajoMinimo(int $sedeId): int
    {
        return Stock::query()
            ->where('sede_id', $sedeId)
            ->where('activo', true)
            ->where('stock_minimo', '>', 0)
            ->whereColumn('cantidad', '<', 'stock_minimo')
            ->whereHas('variante.producto', fn (Builder $p) => $p->where('activo', true))
            ->count();
    }

    /**
     * @return array{clave: string, nivel: string, titulo: string, detalle: string, cantidad: int, to: string}
     */
    private function alerta(string $clave, string $nivel, string $titulo, string $detalle, int $cantidad, string $to): array
    {
        return compact('clave', 'nivel', 'titulo', 'detalle', 'cantidad', 'to');
    }

    private function plural(int $n, string $uno, string $varios): string
    {
        return $n.' '.($n === 1 ? $uno : $varios);
    }
}
