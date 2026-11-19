<?php

namespace App\Http\Requests;

use App\Models\Pago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Venta de tickets sueltos de un sorteo. Payload anidado en `venta`: cliente
 * (registrado: el ticket es suyo), cantidad, precio por ticket (a criterio; 0
 * = de cortesía) y cómo pagó.
 */
class VenderTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $venta = $this->input('venta');
        if (is_array($venta) && is_string($venta['referencia'] ?? null)) {
            $venta['referencia'] = trim($venta['referencia']) ?: null;
            $this->merge(['venta' => $venta]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cobra = (float) $this->input('venta.precio') > 0;
        $metodo = $this->input('venta.metodo');

        return [
            'venta.cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'venta.cantidad' => ['required', 'integer', 'min:1', 'max:1000'],
            'venta.precio' => ['required', 'numeric', 'min:0', 'max:99999.99', 'decimal:0,2'],
            'venta.metodo' => [Rule::requiredIf($cobra), 'nullable', Rule::in(array_keys(Pago::METODOS))],
            'venta.recibido' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'venta.referencia' => [
                Rule::requiredIf($cobra && in_array($metodo, CobrarPedidoRequest::CON_OPERACION, true)),
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
            'venta.cliente_id.required' => 'Elige el cliente: los tickets quedan a su nombre.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'venta.cliente_id' => 'cliente',
            'venta.cantidad' => 'cantidad',
            'venta.precio' => 'precio por ticket',
            'venta.metodo' => 'método de pago',
            'venta.recibido' => 'recibido',
            'venta.referencia' => 'N° de operación',
        ];
    }
}
