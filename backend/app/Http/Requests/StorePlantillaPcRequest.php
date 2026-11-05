<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guarda los casilleros de una PC armada como plantilla. Mismo nombre que
 * una existente = se reemplaza ("Guardar como plantilla" otra vez).
 */
class StorePlantillaPcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['nombre', 'garantia'] as $campo) {
            if (is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo)) ?: null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'garantia' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:'.StoreCotizacionRequest::MAX_ITEMS],
            'items.*.componente' => ['nullable', 'string', 'max:40'],
            'items.*.variante_id' => ['required_without:items.*.descripcion', 'nullable', 'integer', Rule::exists('variantes', 'id')],
            'items.*.descripcion' => ['nullable', 'string', 'max:200'],
            'items.*.unidad' => ['nullable', 'string', 'max:20'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.precio_unitario' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * Sólo lo que se guarda: un ítem del catálogo no lleva descripción.
     *
     * @return array{nombre: string, garantia: ?string, items: list<array<string, mixed>>}
     */
    public function plantilla(): array
    {
        $datos = $this->validated();

        return [
            'nombre' => $datos['nombre'],
            'garantia' => $datos['garantia'] ?? null,
            'items' => array_map(function (array $item) {
                $libre = empty($item['variante_id']);

                return [
                    'componente' => $item['componente'] ?? null,
                    'variante_id' => $libre ? null : (int) $item['variante_id'],
                    'descripcion' => $libre ? $item['descripcion'] : null,
                    'unidad' => $libre ? ($item['unidad'] ?? null) : null,
                    'cantidad' => (float) $item['cantidad'],
                    'precio_unitario' => $libre ? (float) ($item['precio_unitario'] ?? 0) : null,
                ];
            }, array_values($datos['items'])),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'La PC no tiene componentes para guardar.',
            'items.*.variante_id.required_without' => 'Cada componente es del catálogo o va descrito.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la plantilla',
            'items.*.cantidad' => 'cantidad',
            'items.*.precio_unitario' => 'precio',
        ];
    }
}
