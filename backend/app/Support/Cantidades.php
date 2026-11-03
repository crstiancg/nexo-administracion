<?php

namespace App\Support;

use App\Models\Stock;
use App\Models\Variante;
use Closure;

/**
 * Reglas de cantidad compartidas por inventario, pedidos y punto de venta.
 */
class Cantidades
{
    /**
     * Base de toda cantidad: positiva, hasta 3 decimales.
     *
     * @return array<int, string>
     */
    public static function positiva(float $maximo = 100000): array
    {
        return ['required', 'numeric', 'gt:0', 'max:'.$maximo, 'decimal:0,3'];
    }

    /**
     * Una bolsa o un balde no se venden por la mitad: con una unidad que no
     * es fraccionable la cantidad tiene que ser entera.
     */
    public static function segunUnidad(mixed $varianteId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($varianteId) {
            if (! is_numeric($varianteId) || ! is_numeric($value) || floor((float) $value) == (float) $value) {
                return;
            }

            $unidad = Variante::query()->whereKey($varianteId)->with('unidad:id,nombre,fraccionable')->first()?->unidad;

            if ($unidad && ! $unidad->fraccionable) {
                $fail("Se vende por {$unidad->nombre} entera: la cantidad no lleva decimales.");
            }
        };
    }

    /**
     * Stock de una presentación en una sede (0 si nunca tuvo; null si todavía
     * no se eligió la presentación).
     */
    public static function stockEnSede(mixed $varianteId, ?int $sedeId): ?float
    {
        if (! is_numeric($varianteId) || ! $sedeId) {
            return null;
        }

        return (float) Stock::query()->where('variante_id', $varianteId)->where('sede_id', $sedeId)->value('cantidad');
    }
}
