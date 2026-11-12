<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    /**
     * SQL del día local (YYYY-MM-DD) de una columna datetime guardada en UTC,
     * para agrupar ventas por día: una venta de las 20:00 en Lima es de hoy.
     * Cada motor lo escribe distinto (producción en Postgres, local en MySQL,
     * tests en SQLite).
     */
    public static function diaSql(string $columna): string
    {
        $minutos = self::desfaseMinutos();

        return match (DB::connection()->getDriverName()) {
            'pgsql' => "CAST({$columna} + INTERVAL '{$minutos} minutes' AS DATE)",
            'sqlite' => "DATE({$columna}, '{$minutos} minutes')",
            default => "DATE(DATE_ADD({$columna}, INTERVAL {$minutos} MINUTE))",
        };
    }

    /**
     * SQL del mes local (YYYY-MM) de una columna datetime guardada en UTC.
     */
    public static function mesSql(string $columna): string
    {
        $minutos = self::desfaseMinutos();

        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR({$columna} + INTERVAL '{$minutos} minutes', 'YYYY-MM')",
            'sqlite' => "STRFTIME('%Y-%m', {$columna}, '{$minutos} minutes')",
            default => "DATE_FORMAT(DATE_ADD({$columna}, INTERVAL {$minutos} MINUTE), '%Y-%m')",
        };
    }

    /**
     * Minutos entre la zona del negocio y UTC (-300 en Lima).
     */
    private static function desfaseMinutos(): int
    {
        return (int) now(self::zona())->utcOffset();
    }
}
