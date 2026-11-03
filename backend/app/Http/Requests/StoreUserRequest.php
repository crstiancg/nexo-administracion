<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y actualiza usuarios. Payload anidado en `usuario`, con los IDs de
 * roles y permisos directos en `rolesSelected` / `permisosSelected`.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El modelo guarda username y email en minúsculas; se normalizan antes de
     * validar para que `unique` compare lo mismo que se va a guardar (en
     * SQLite la comparación distingue mayúsculas, en MySQL no).
     */
    protected function prepareForValidation(): void
    {
        $usuario = $this->input('usuario');
        if (! is_array($usuario)) {
            return;
        }

        foreach (['username', 'email'] as $campo) {
            if (is_string($usuario[$campo] ?? null)) {
                $usuario[$campo] = mb_strtolower(trim($usuario[$campo]));
            }
        }

        $this->merge(['usuario' => $usuario]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $creando = $usuario === null;

        return [
            'usuario.name' => ['required', 'string', 'max:255'],
            'usuario.username' => [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($usuario),
            ],
            'usuario.email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($usuario),
            ],
            // Al editar, vacía = no cambiarla.
            'usuario.password' => [$creando ? 'required' : 'nullable', 'string', 'min:8'],
            'usuario.sede_id' => ['nullable', 'integer', 'exists:sedes,id'],
            'usuario.rolesSelected' => ['sometimes', 'array'],
            'usuario.rolesSelected.*' => ['integer', Rule::exists('roles', 'id')->where('guard_name', 'api')],
            'usuario.permisosSelected' => ['sometimes', 'array'],
            'usuario.permisosSelected.*' => ['integer', Rule::exists('permissions', 'id')->where('guard_name', 'api')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuario.username.regex' => 'El usuario sólo puede tener letras, números, puntos, guiones y guiones bajos.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'usuario.name' => 'nombre',
            'usuario.username' => 'usuario',
            'usuario.email' => 'email',
            'usuario.password' => 'contraseña',
            'usuario.sede_id' => 'sede',
            'usuario.rolesSelected.*' => 'rol',
            'usuario.permisosSelected.*' => 'permiso',
        ];
    }
}
