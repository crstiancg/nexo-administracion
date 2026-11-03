<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Completar o corregir lo que se olvidó cargar en una entrada: costo,
 * referencia (factura / guía) y código y vencimiento de sus lotes. La
 * cantidad no: eso se corrige con otro movimiento. Payload en `entrada`.
 */
class CorregirEntradaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entrada.costo_unitario' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'entrada.referencia' => ['nullable', 'string', 'max:60'],
            'entrada.lotes' => ['array'],
            'entrada.lotes.*.id' => ['required', 'integer'],
            'entrada.lotes.*.codigo' => ['required', 'string', 'max:40'],
            // Sin after_or_equal: se corrige mercadería que ya entró (y puede
            // estar vencida).
            'entrada.lotes.*.vence_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'entrada.costo_unitario' => 'costo unitario',
            'entrada.referencia' => 'referencia',
            'entrada.lotes.*.codigo' => 'lote',
            'entrada.lotes.*.vence_at' => 'vencimiento',
        ];
    }
}
