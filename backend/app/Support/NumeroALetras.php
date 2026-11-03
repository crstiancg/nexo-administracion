<?php

namespace App\Support;

class NumeroALetras
{
    private const UNIDADES = [
        '', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE',
        'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE',
        'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
    ];

    private const DECENAS = [
        '', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA',
        'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA',
    ];

    private const CENTENAS = [
        '', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS',
        'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS',
    ];

    public static function convertir(float $monto, string $moneda = 'SOLES'): string
    {
        $entero = (int) floor($monto);
        $centavos = (int) round(($monto - $entero) * 100);

        return sprintf('SON %s CON %02d/100 %s', self::enteroALetras($entero), $centavos, $moneda);
    }

    private static function enteroALetras(int $n): string
    {
        if ($n === 0) {
            return 'CERO';
        }

        if ($n < 1000) {
            return self::menorMil($n);
        }

        $miles = intdiv($n, 1000);
        $resto = $n % 1000;

        $prefijo = $miles === 1 ? 'MIL' : self::enteroALetras($miles).' MIL';

        return trim($prefijo.($resto ? ' '.self::menorMil($resto) : ''));
    }

    private static function menorMil(int $n): string
    {
        if ($n === 100) {
            return 'CIEN';
        }

        $centena = intdiv($n, 100);
        $resto = $n % 100;

        $texto = $centena ? self::CENTENAS[$centena] : '';
        if ($resto) {
            $texto .= ($texto ? ' ' : '').self::menorCien($resto);
        }

        return $texto;
    }

    private static function menorCien(int $n): string
    {
        if ($n < 20) {
            return self::UNIDADES[$n];
        }

        $decena = intdiv($n, 10);
        $unidad = $n % 10;

        if ($decena === 2 && $unidad) {
            return 'VEINTI'.self::UNIDADES[$unidad];
        }

        return self::DECENAS[$decena].($unidad ? ' Y '.self::UNIDADES[$unidad] : '');
    }
}
