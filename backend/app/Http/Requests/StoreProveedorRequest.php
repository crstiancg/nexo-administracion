<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y actualiza proveedores. Payload anidado en `proveedor`.
 */
class StoreProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $proveedor = $this->input('proveedor');
        if (! is_array($proveedor)) {
            return;
        }

        foreach (['ruc', 'razon_social', 'contacto', 'telefono', 'email', 'direccion'] as $campo) {
            if (is_string($proveedor[$campo] ?? null)) {
                $proveedor[$campo] = trim($proveedor[$campo]) ?: null;
            }
        }
        if (is_string($proveedor['email'] ?? null)) {
            $proveedor['email'] = mb_strtolower($proveedor['email']);
        }

        $this->merge(['proveedor' => $proveedor]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'proveedor.ruc' => [
                'required', 'regex:/^(10|15|17|20)\d{9}$/',
                Rule::unique('proveedores', 'ruc')->ignore($this->route('proveedor')),
            ],
            'proveedor.razon_social' => ['required', 'string', 'max:150'],
            'proveedor.contacto' => ['nullable', 'string', 'max:100'],
            'proveedor.telefono' => ['nullable', 'string', 'max:30'],
            'proveedor.email' => ['nullable', 'email', 'max:120'],
            'proveedor.direccion' => ['nullable', 'string', 'max:255'],
            'proveedor.activo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proveedor.ruc.regex' => 'El RUC tiene 11 dígitos y empieza con 10, 15, 17 o 20.',
            'proveedor.ruc.unique' => 'Ya hay un proveedor con ese RUC.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'proveedor.ruc' => 'RUC',
            'proveedor.razon_social' => 'razón social',
            'proveedor.contacto' => 'contacto',
            'proveedor.telefono' => 'teléfono',
            'proveedor.email' => 'email',
            'proveedor.direccion' => 'dirección',
        ];
    }
}
