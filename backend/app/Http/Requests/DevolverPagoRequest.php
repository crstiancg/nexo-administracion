<?php

namespace App\Http\Requests;

use App\Models\Pago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Devolución de dinero de un pedido. Payload anidado en `pago`. El motivo es
 * obligatorio: una salida de plata sin explicación no se acepta.
 */
class DevolverPagoRequest extends FormRequest
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
            'pago.metodo' => ['required', Rule::in(array_keys(Pago::METODOS))],
            'pago.monto' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'pago.motivo' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pago.metodo' => 'método',
            'pago.monto' => 'monto',
            'pago.motivo' => 'motivo',
        ];
    }
}
