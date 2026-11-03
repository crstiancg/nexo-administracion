<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y actualiza roles. El payload viene anidado en `rol`, con los IDs de
 * los permisos tildados en `rol.permisosSelected`.
 */
class StoreRolRequest extends FormRequest
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
            'rol.name' => [
                'required', 'string', 'max:125',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'api')
                    ->ignore($this->route('role')),
            ],
            'rol.permisosSelected' => ['sometimes', 'array'],
            'rol.permisosSelected.*' => ['integer', Rule::exists('permissions', 'id')->where('guard_name', 'api')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rol.name' => 'nombre',
            'rol.permisosSelected.*' => 'permiso',
        ];
    }
}
