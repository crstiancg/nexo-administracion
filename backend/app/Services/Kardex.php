<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\Variante;
use App\Support\Fechas;
use Carbon\CarbonInterface;

/**
 * Kardex valorizado de una presentación: cada movimiento con su entrada o
 * salida y el saldo, en cantidad y en soles, al costo promedio ponderado.
 *
 * El costo es el de la empresa (como Inventario::entrada()): se recorre todo
 * el historial de la presentación para tenerlo al día en cada fecha. Con
 * sede, las cantidades y el saldo son sólo los de esa sede (los traslados
 * cuentan como entrada o salida). Lo anterior a `desde` va como saldo inicial.
 */
class Kardex
{
    private const EPSILON = 0.0005;

    /**
     * @return array{saldo_inicial: array<string, float|null>, filas: list<array<string, mixed>>, totales: array<string, float>, saldo_final: array<string, float|null>}
     */
    public function de(Variante $variante, ?int $sedeId, ?CarbonInterface $desde, ?CarbonInterface $hasta): array
    {
        $movimientos = MovimientoInventario::query()
            ->with(['usuario:id,name', 'sede:id,nombre'])
            ->where('variante_id', $variante->id)
            ->orderBy('id')
            ->get();

        $inicio = $desde ? Fechas::inicioDelDia($desde) : null;
        $fin = $hasta ? Fechas::finDelDia($hasta) : null;

        $stockEmpresa = 0.0;
        $costo = null;
        $saldo = 0.0;
        $inicial = null;
        $filas = [];
        $totales = ['entradas' => 0.0, 'entradas_valor' => 0.0, 'salidas' => 0.0, 'salidas_valor' => 0.0];

        foreach ($movimientos as $m) {
            $cantidad = (float) $m->cantidad;

            // Promedio ponderado de la empresa (igual que Inventario::entrada()).
            if ($m->tipo === MovimientoInventario::ENTRADA && $m->costo_unitario !== null && $cantidad > 0) {
                $total = max(0.0, $stockEmpresa);
                $unitario = (float) $m->costo_unitario;
                $costo = $costo === null || $total < self::EPSILON
                    ? $unitario
                    : round((($total * $costo) + ($cantidad * $unitario)) / ($total + $cantidad), 4);
            }
            $stockEmpresa += $cantidad;

            if ($sedeId && $m->sede_id !== $sedeId) {
                continue;
            }
            // continue y no break: el costo sigue el orden de registro (id).
            if ($fin && $m->created_at->gt($fin)) {
                continue;
            }

            // Lo de antes del período: sólo mueve el saldo inicial.
            if ($inicio && $m->created_at->lt($inicio)) {
                $saldo += $cantidad;
                $inicial = ['cantidad' => round($saldo, 3), 'costo' => $costo, 'valor' => $this->valor($saldo, $costo)];

                continue;
            }

            $saldo += $cantidad;
            // La entrada a su costo de compra; la salida al promedio del momento.
            $unitario = $cantidad > 0 && $m->costo_unitario !== null ? (float) $m->costo_unitario : $costo;
            $importe = $this->valor(abs($cantidad), $unitario);

            if ($cantidad > 0) {
                $totales['entradas'] += $cantidad;
                $totales['entradas_valor'] += $importe ?? 0;
            } else {
                $totales['salidas'] += -$cantidad;
                $totales['salidas_valor'] += $importe ?? 0;
            }

            $filas[] = [
                'id' => $m->id,
                'fecha' => $m->created_at->toIso8601String(),
                'tipo' => $m->tipo,
                'motivo' => $m->motivo,
                'detalle' => $this->detalle($m),
                'sede' => $m->sede?->nombre,
                'usuario' => $m->usuario?->name,
                'entrada' => $cantidad > 0 ? round($cantidad, 3) : null,
                'salida' => $cantidad < 0 ? round(-$cantidad, 3) : null,
                'costo_unitario' => $unitario !== null ? round($unitario, 4) : null,
                'importe' => $importe,
                'saldo' => round($saldo, 3),
                'costo_promedio' => $costo !== null ? round($costo, 4) : null,
                'saldo_valor' => $this->valor($saldo, $costo),
            ];
        }

        return [
            'saldo_inicial' => $inicial ?? ['cantidad' => 0.0, 'costo' => null, 'valor' => 0.0],
            'filas' => $filas,
            'totales' => array_map(fn ($v) => round($v, 3), $totales),
            'saldo_final' => ['cantidad' => round($saldo, 3), 'costo' => $costo, 'valor' => $this->valor($saldo, $costo)],
        ];
    }

    private function valor(float $cantidad, ?float $costo): ?float
    {
        return $costo === null ? null : round($cantidad * $costo, 2);
    }

    /** "Compra C-000012", "Venta P-000031", "Traslado", "Merma"… */
    private function detalle(MovimientoInventario $m): string
    {
        $motivo = MovimientoInventario::MOTIVOS_SALIDA[$m->motivo] ?? match ($m->motivo) {
            MovimientoInventario::MOTIVO_VENTA => 'Venta',
            MovimientoInventario::MOTIVO_DEVOLUCION_VENTA => 'Devolución de venta',
            MovimientoInventario::MOTIVO_COMPRA => 'Compra',
            MovimientoInventario::MOTIVO_ANULACION_COMPRA => 'Anulación de compra',
            MovimientoInventario::MOTIVO_ALTA_PRODUCTO => 'Stock inicial',
            MovimientoInventario::MOTIVO_TRASLADO_SALIDA => 'Traslado (salida)',
            MovimientoInventario::MOTIVO_TRASLADO_ENTRADA => 'Traslado (entrada)',
            MovimientoInventario::MOTIVO_CONTEO => 'Ajuste por conteo',
            default => ucfirst((string) ($m->motivo ?? $m->tipo)),
        };

        return trim($motivo.' '.($m->referencia ?? ''));
    }
}
