<?php

namespace App\Http\Requests;

use App\Models\Unidad;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Crea y actualiza unidades de medida. Payload anidado en `unidad`.
 */
class StoreUnidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La abreviatura va en mayúsculas: así sale igual en tickets y etiquetas.
     */
    protected function prepareForValidation(): void
    {
        $unidad = $this->input('unidad');
        if (! is_array($unidad)) {
            return;
        }

        if (is_string($unidad['nombre'] ?? null)) {
            $unidad['nombre'] = trim($unidad['nombre']);
        }
        if (is_string($unidad['abreviatura'] ?? null)) {
            $unidad['abreviatura'] = mb_strtoupper(trim($unidad['abreviatura']));
        }
        // Código SUNAT (catálogo 03): vacío = NIU, la "unidad" genérica.
        if (array_key_exists('codigo_sunat', $unidad)) {
            $unidad['codigo_sunat'] = mb_strtoupper(trim((string) $unidad['codigo_sunat'])) ?: 'NIU';
        }

        $this->merge(['unidad' => $unidad]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unidad = $this->route('unidad');

        return [
            'unidad.nombre' => ['required', 'string', 'max:40', $this->unico('nombre', $unidad, 'Ya existe una unidad con ese nombre.')],
            'unidad.codigo_sunat' => ['sometimes', 'string', 'regex:/^[A-Z0-9]{2,3}$/'],
            'unidad.abreviatura' => ['required', 'string', 'max:10', $this->unico('abreviatura', $unidad, 'Ya existe una unidad con esa abreviatura.')],
            'unidad.fraccionable' => ['required', 'boolean'],
        ];
    }

    private function unico(string $columna, ?Unidad $ignorar, string $mensaje): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($columna, $ignorar, $mensaje) {
            $existe = Unidad::query()
                ->whereRaw("LOWER({$columna}) = ?", [mb_strtolower((string) $value)])
                ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar->getKey()))
                ->exists();

            if ($existe) {
                $fail($mensaje);
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'unidad.nombre' => 'nombre',
            'unidad.abreviatura' => 'abreviatura',
            'unidad.fraccionable' => 'fraccionable',
        ];
    }
}
