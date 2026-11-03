<?php

namespace App\Support;

/**
 * IGV (18%), aplicado a todo. Los precios del sistema YA lo incluyen (es lo
 * que paga el cliente): el total no cambia, sólo se desglosa en operación
 * gravada + IGV para el ticket, la cotización y los reportes.
 */
class Igv
{
    public static function tasa(): float
    {
        return (float) config('app.igv');
    }

    /**
     * @return array{op_gravada: float, igv: float}
     */
    public static function desglosar(float|string|null $total): array
    {
        $total = round((float) $total, 2);
        $gravada = round($total / (1 + self::tasa()), 2);

        // El IGV es la diferencia: base + IGV suman exacto el total.
        return ['op_gravada' => $gravada, 'igv' => round($total - $gravada, 2)];
    }
}
