<?php

namespace App\Http\Requests;

use App\Support\Fechas;
use App\Support\Sedes;
use Illuminate\Validation\Rule;

/**
 * Compra o reposición: cada línea con cantidad y costo unitario y, si el
 * producto maneja lotes, el lote y su vencimiento.
 */
class RegistrarEntradaRequest extends MovimientoInventarioRequest
{
    protected function reglasDeLinea(int|string $i): array
    {
        return [
            "movimiento.lineas.{$i}.cantidad" => [
                ...$this->reglasCantidad($i),
                Sedes::habilitada($this->input("movimiento.lineas.{$i}.variante_id"), $this->user()?->sede_id),
            ],
            "movimiento.lineas.{$i}.costo_unitario" => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            "movimiento.lineas.{$i}.lote" => [Rule::requiredIf(fn () => $this->manejaLotes($i)), 'nullable', 'string', 'max:40'],
            // No se recibe mercadería ya vencida.
            "movimiento.lineas.{$i}.vence_at" => [Rule::requiredIf(fn () => $this->manejaLotes($i)), 'nullable', 'date', 'after_or_equal:'.Fechas::hoyIso()],
        ];
    }
}
