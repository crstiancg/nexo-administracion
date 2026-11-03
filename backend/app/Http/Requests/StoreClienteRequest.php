<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y actualiza clientes. Payload anidado en `cliente`. El documento es
 * opcional (un cliente de WhatsApp puede dejar sólo nombre y teléfono), pero
 * si viene, tiene que ser válido y único por tipo.
 */
class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $cliente = $this->input('cliente');
        if (! is_array($cliente)) {
            return;
        }

        foreach (['nombre', 'telefono', 'email', 'direccion'] as $campo) {
            if (is_string($cliente[$campo] ?? null)) {
                $cliente[$campo] = trim(preg_replace('/\s+/', ' ', $cliente[$campo])) ?: null;
            }
        }

        if (is_string($cliente['numero_documento'] ?? null)) {
            $cliente['numero_documento'] = mb_strtoupper(preg_replace('/\s+/', '', $cliente['numero_documento'])) ?: null;
        }

        // Sin número, el tipo no significa nada (y al revés lo valida la regla).
        if (empty($cliente['numero_documento'])) {
            $cliente['tipo_documento'] = null;
        }

        $this->merge(['cliente' => $cliente]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cliente = $this->route('cliente');
        $tipo = $this->input('cliente.tipo_documento');

        $formato = match ($tipo) {
            Cliente::DNI => ['digits:8'],
            Cliente::RUC => ['digits:11', 'regex:/^(10|15|17|20)\d{9}$/'],
            Cliente::CE => ['alpha_num', 'between:9,12'],
            default => [],
        };

        return [
            'cliente.tipo_documento' => ['nullable', 'required_with:cliente.numero_documento', Rule::in(Cliente::TIPOS_DOCUMENTO)],
            'cliente.numero_documento' => [
                'nullable', 'string', ...$formato,
                Rule::unique('clientes', 'numero_documento')
                    ->where('tipo_documento', $tipo)
                    ->ignore($cliente),
            ],
            'cliente.nombre' => ['required', 'string', 'max:150'],
            'cliente.telefono' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 +()-]{6,20}$/'],
            'cliente.email' => ['nullable', 'email', 'max:120'],
            'cliente.direccion' => ['nullable', 'string', 'max:255'],
            'cliente.mayorista' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cliente.numero_documento.digits' => 'El :attribute debe tener :digits dígitos.',
            'cliente.numero_documento.regex' => 'El RUC debe empezar con 10, 15, 17 o 20.',
            'cliente.numero_documento.unique' => 'Ya hay un cliente con ese documento.',
            'cliente.telefono.regex' => 'El teléfono sólo puede tener números, espacios, +, - y paréntesis.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cliente.tipo_documento' => 'tipo de documento',
            'cliente.numero_documento' => 'número de documento',
            'cliente.nombre' => 'nombre',
            'cliente.telefono' => 'teléfono',
            'cliente.email' => 'email',
            'cliente.direccion' => 'dirección',
        ];
    }
}
