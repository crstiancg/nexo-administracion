<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * "Hoy" del negocio. La app guarda y calcula en UTC, pero el día (qué lote
 * venció, qué ventas son de hoy) es el de la zona del negocio: con today()
 * en UTC, desde las 19:00 de Lima el sistema ya creía que era mañana.
 */
class Fechas
{
    public static function zona(): string
    {
        return config('app.zona_negocio');
    }

    /**
     * La fecha de hoy en la zona del negocio, a las 00:00 de la zona de la
     * app: se compara directo con columnas `date` (vence_at, valida_hasta).
     */
    public static function hoy(): Carbon
    {
        return Carbon::parse(now(self::zona())->toDateString());
    }

    /**
     * Para reglas de validación: 'after_or_equal:'.Fechas::hoyIso().
     */
    public static function hoyIso(): string
    {
        return now(self::zona())->toDateString();
    }

    /**
     * Inicio del día local `$fecha` (hoy si es null) expresado en UTC: para
     * filtrar columnas datetime (created_at, confirmado_at) por día local.
     */
    public static function inicioDelDia(?CarbonInterface $fecha = null): Carbon
    {
        return Carbon::parse(($fecha ?? self::hoy())->toDateString(), self::zona())->startOfDay()->utc();
    }

    public static function finDelDia(?CarbonInterface $fecha = null): Carbon
    {
        return Carbon::parse(($fecha ?? self::hoy())->toDateString(), self::zona())->endOfDay()->utc();
    }
}
