<?php

namespace App\Http\Requests;

use App\Models\Pago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cobro de un pedido. Payload anidado en `pago`. Que no supere el saldo y que
 * lo recibido alcance lo decide App\Services\Cajas con el pedido bloqueado.
 */
class CobrarPedidoRequest extends FormRequest
{
    /** Métodos que se verifican contra el celular o el banco. */
    public const CON_OPERACION = ['yape', 'plin', 'transferencia'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $pago = $this->input('pago');
        if (is_array($pago) && is_string($pago['referencia'] ?? null)) {
            $pago['referencia'] = trim($pago['referencia']) ?: null;
            $this->merge(['pago' => $pago]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pago.metodo' => ['required', Rule::in(array_keys(Pago::METODOS))],
            'pago.monto' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            // Sólo efectivo: lo que entregó el cliente (para el vuelto).
            'pago.recibido' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'pago.referencia' => [
                Rule::requiredIf(in_array($this->input('pago.metodo'), self::CON_OPERACION, true)),
                'nullable', 'string', 'max:40',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pago.referencia.required' => 'Anotá el número de operación para poder verificar el pago.',
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
            'pago.recibido' => 'recibido',
            'pago.referencia' => 'n° de operación',
        ];
    }
}
