<?php

namespace App\Support;

use App\Models\Stock;
use Closure;

/**
 * Reglas sobre lo que cada sede vende (la configuración por sede vive en
 * `stocks`: activo, precio y stock mínimo).
 */
class Sedes
{
    /**
     * Para vender, pedir, cotizar o comprar: la presentación tiene que estar
     * habilitada en la sede del usuario.
     */
    public static function habilitada(mixed $varianteId, ?int $sedeId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($varianteId, $sedeId) {
            if (! is_numeric($varianteId) || ! $sedeId) {
                return;
            }

            $habilitada = Stock::query()
                ->where('variante_id', $varianteId)
                ->where('sede_id', $sedeId)
                ->where('activo', true)
                ->exists();

            if (! $habilitada) {
                $fail('Esta presentación no se vende en tu sede: habilitala en el producto.');
            }
        };
    }
}
