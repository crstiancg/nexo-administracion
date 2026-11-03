<?php

namespace App\Http\Requests;

use App\Support\Cantidades;
use Illuminate\Validation\Rule;

/**
 * Conteo físico en la sede del usuario: por cada presentación, el stock REAL
 * que se contó.
 */
class RegistrarAjusteRequest extends MovimientoInventarioRequest
{
    protected function reglasDeLinea(int|string $i): array
    {
        return [
            "movimiento.lineas.{$i}.stock_real" => [
                'required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,3',
                Cantidades::segunUnidad($this->input("movimiento.lineas.{$i}.variante_id")),
            ],
            // Lo que sobra entra a un lote: hace falta saber a cuál. Lo que
            // falta sale del que vence primero.
            "movimiento.lineas.{$i}.lote" => [Rule::requiredIf(fn () => $this->sobra($i)), 'nullable', 'string', 'max:40'],
            "movimiento.lineas.{$i}.vence_at" => [Rule::requiredIf(fn () => $this->sobra($i)), 'nullable', 'date'],
        ];
    }

    private function sobra(int|string $i): bool
    {
        $real = $this->input("movimiento.lineas.{$i}.stock_real");

        return is_numeric($real) && $this->manejaLotes($i) && (float) $real > ($this->stockDe($i) ?? 0) + 0.0005;
    }
}
