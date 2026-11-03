<?php

namespace App\Http\Requests;

use App\Models\Marca;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Crea y actualiza marcas. Payload anidado en `marca`.
 */
class StoreMarcaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $marca = $this->input('marca');
        if (! is_array($marca)) {
            return;
        }

        if (is_string($marca['nombre'] ?? null)) {
            $marca['nombre'] = trim($marca['nombre']);
        }

        $this->merge(['marca' => $marca]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'marca.nombre' => ['required', 'string', 'max:60', $this->nombreUnico($this->route('marca'))],
            'marca.activo' => ['required', 'boolean'],
        ];
    }

    /**
     * "Sika" y "SIKA" son la misma marca (SQLite no compara sin mayúsculas).
     */
    private function nombreUnico(?Marca $ignorar): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignorar) {
            $existe = Marca::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $value)])
                ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar->getKey()))
                ->exists();

            if ($existe) {
                $fail('Ya existe una marca con ese nombre.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'marca.nombre' => 'nombre',
            'marca.activo' => 'activo',
        ];
    }
}
