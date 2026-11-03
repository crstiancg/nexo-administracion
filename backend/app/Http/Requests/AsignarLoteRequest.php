<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Darle lote al stock que quedó sin lote en la sede del usuario (stock
 * inicial cargado antes de activar lotes en el producto). Payload en `lote`.
 */
class AsignarLoteRequest extends FormRequest
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
            'lote.variante_id' => ['required', 'integer', 'exists:variantes,id'],
            'lote.codigo' => ['required', 'string', 'max:40'],
            'lote.vence_at' => ['nullable', 'date'],
            'lote.cantidad' => ['required', 'numeric', 'gt:0', 'max:99999999.999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lote.codigo' => 'lote',
            'lote.vence_at' => 'vencimiento',
            'lote.cantidad' => 'cantidad',
        ];
    }
}
