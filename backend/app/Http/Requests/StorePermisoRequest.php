<?php

namespace App\Http\Requests;

use App\Support\RutasProtegidas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear permisos = elegir rutas protegidas que todavía no tienen el suyo. No
 * se acepta un nombre libre: sólo los de la lista, así no hay typos.
 */
class StorePermisoRequest extends FormRequest
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
        $disponibles = app(RutasProtegidas::class)->disponibles()->keys()->all();

        return [
            'permiso.rutas' => ['required', 'array', 'min:1'],
            'permiso.rutas.*' => ['string', 'distinct', Rule::in($disponibles)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permiso.rutas.required' => 'Elegí al menos una ruta.',
            'permiso.rutas.min' => 'Elegí al menos una ruta.',
            'permiso.rutas.*.in' => 'La ruta :input no existe o ya tiene su permiso.',
        ];
    }
}
