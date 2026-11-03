<?php

namespace App\Http\Requests;

use App\Models\MovimientoInventario;
use Illuminate\Validation\Rule;

/**
 * Salida manual (merma, daño, regalo…). Las ventas descuentan desde Pedidos.
 */
class RegistrarSalidaRequest extends MovimientoInventarioRequest
{
    protected function reglasDelDocumento(): array
    {
        return [
            'movimiento.motivo' => ['required', Rule::in(array_keys(MovimientoInventario::MOTIVOS_SALIDA))],
        ];
    }

    protected function reglasDeLinea(int|string $i): array
    {
        return [
            "movimiento.lineas.{$i}.cantidad" => [...$this->reglasCantidad($i), $this->hayStock($i)],
            // Opcional: sin lote sale del que vence primero.
            "movimiento.lineas.{$i}.lote_id" => [
                'nullable', 'integer',
                Rule::exists('lotes', 'id')
                    ->where('variante_id', (int) $this->input("movimiento.lineas.{$i}.variante_id"))
                    ->where('sede_id', (int) $this->user()?->sede_id),
            ],
        ];
    }
}
