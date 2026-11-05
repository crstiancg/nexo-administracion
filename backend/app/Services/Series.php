<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\Serie;
use App\Models\Variante;

/**
 * Números de serie de los productos que `maneja_series`. Los mueve
 * Inventario::registrar() junto a cada movimiento, en la misma transacción
 * (un error en una línea deshace todo):
 *
 * - Lo que entra (compra, entrada, devolución, traslado, sobrante de un
 *   conteo) trae `series`: un número por unidad. Uno que ya está en stock no
 *   puede entrar de nuevo.
 * - Lo que sale (venta, merma, traslado, faltante) puede traer `series` (las
 *   que se entregan); si no, salen las que entraron primero.
 */
class Series
{
    /**
     * @param  array<string, mixed>  $linea  la línea del movimiento
     * @param  array<string, string>  $errores
     */
    public function mover(MovimientoInventario $movimiento, Variante $variante, int $sedeId, array $linea, int $i, array &$errores): void
    {
        $cantidad = (float) $movimiento->cantidad;
        $unidades = (int) round(abs($cantidad));
        $clave = "movimiento.lineas.{$i}.series";

        if (abs(abs($cantidad) - $unidades) > 0.0005) {
            $errores["movimiento.lineas.{$i}.cantidad"] = 'Un producto con número de serie se mueve por unidades enteras.';

            return;
        }

        $numeros = collect($linea['series'] ?? [])
            ->map(fn ($n) => Serie::normalizar((string) $n))
            ->filter()
            ->values();

        if ($numeros->duplicates()->isNotEmpty()) {
            $errores[$clave] = 'La serie '.$numeros->duplicates()->first().' está repetida.';

            return;
        }

        $cantidad > 0
            ? $this->entrar($movimiento, $variante, $sedeId, $numeros->all(), $unidades, $clave, $errores)
            : $this->sacar($movimiento, $variante, $sedeId, $numeros->all(), $unidades, $clave, $errores);
    }

    /**
     * @param  string[]  $numeros
     * @param  array<string, string>  $errores
     */
    private function entrar(MovimientoInventario $movimiento, Variante $variante, int $sedeId, array $numeros, int $unidades, string $clave, array &$errores): void
    {
        if (count($numeros) !== $unidades) {
            $errores[$clave] = $unidades === 1
                ? 'Este producto maneja números de serie: escribe la serie de la unidad.'
                : "Este producto maneja números de serie: escribe las {$unidades} series (van ".count($numeros).').';

            return;
        }

        $existentes = Serie::query()
            ->where('variante_id', $variante->id)
            ->whereIn('numero', $numeros)
            ->lockForUpdate()
            ->get()
            ->keyBy('numero');

        $enStock = $existentes->filter(fn (Serie $s) => $s->estado === Serie::EN_STOCK);
        if ($enStock->isNotEmpty()) {
            $errores[$clave] = 'La serie '.$enStock->keys()->first().' ya está en stock: no puede entrar dos veces.';

            return;
        }

        foreach ($numeros as $numero) {
            // Una que había salido (devolución, re-compra) vuelve a la misma fila.
            $serie = $existentes[$numero] ?? new Serie(['variante_id' => $variante->id, 'numero' => $numero]);
            $serie->forceFill(['estado' => Serie::EN_STOCK, 'sede_id' => $sedeId])->save();
            $movimiento->series()->attach($serie->id);
        }
    }

    /**
     * @param  string[]  $numeros
     * @param  array<string, string>  $errores
     */
    private function sacar(MovimientoInventario $movimiento, Variante $variante, int $sedeId, array $numeros, int $unidades, string $clave, array &$errores): void
    {
        $disponibles = Serie::query()
            ->where('variante_id', $variante->id)
            ->where('sede_id', $sedeId)
            ->where('estado', Serie::EN_STOCK)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($numeros) {
            if (count($numeros) !== $unidades) {
                $errores[$clave] = "Elige {$unidades} ".($unidades === 1 ? 'serie' : 'series').' (van '.count($numeros).').';

                return;
            }
            $faltan = array_diff($numeros, $disponibles->pluck('numero')->all());
            if ($faltan) {
                $errores[$clave] = 'La serie '.reset($faltan).' no está en stock en esta sede.';

                return;
            }
            $salen = $disponibles->whereIn('numero', $numeros);
        } else {
            // Sin elegir: las que entraron primero. Lo que falte puede ser
            // stock de antes de activar las series ("sin serie"): sale igual.
            $stockAntes = (float) $movimiento->stock_resultante + $unidades;
            $sinSerie = (int) round(max(0, $stockAntes - $disponibles->count()));
            if ($disponibles->count() + $sinSerie < $unidades) {
                $errores[$clave] = 'Sólo hay '.$disponibles->count().' con número de serie en esta sede.';

                return;
            }
            $salen = $disponibles->take(min($unidades, $disponibles->count()));
        }

        $estado = $movimiento->motivo === MovimientoInventario::MOTIVO_VENTA ? Serie::VENDIDA : Serie::BAJA;
        foreach ($salen as $serie) {
            $serie->forceFill(['estado' => $estado, 'sede_id' => $estado === Serie::BAJA ? $sedeId : null])->save();
            $movimiento->series()->attach($serie->id);
        }
    }

    /**
     * Los números de un movimiento: para que vuelvan las mismas (devolución
     * de una venta, anulación de una compra, traslado).
     *
     * @return string[]
     */
    public static function de(?MovimientoInventario $movimiento): array
    {
        return $movimiento ? $movimiento->series()->pluck('numero')->all() : [];
    }
}
