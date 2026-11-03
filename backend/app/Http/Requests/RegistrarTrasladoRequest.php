<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Validation\Rule;

/**
 * Traslado desde la sede del usuario a otra sede activa.
 */
class RegistrarTrasladoRequest extends MovimientoInventarioRequest
{
    protected function reglasDelDocumento(): array
    {
        return [
            'movimiento.sede_destino_id' => [
                'required', 'integer',
                Rule::exists('sedes', 'id')->where('activo', true),
                $this->otraSede(),
            ],
        ];
    }

    protected function reglasDeLinea(int|string $i): array
    {
        return [
            "movimiento.lineas.{$i}.cantidad" => [...$this->reglasCantidad($i), $this->hayStock($i)],
        ];
    }

    private function otraSede(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ((int) $value === (int) $this->user()?->sede_id) {
                $fail('El destino tiene que ser otra sede.');
            }
        };
    }
}
