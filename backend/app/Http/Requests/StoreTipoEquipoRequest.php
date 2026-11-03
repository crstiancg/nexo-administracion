<?php

namespace App\Http\Requests;

use App\Models\TipoEquipo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Crea y actualiza tipos de equipo. Payload anidado en `tipo`.
 */
class StoreTipoEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tipo = $this->input('tipo');
        if (! is_array($tipo)) {
            return;
        }

        if (is_string($tipo['nombre'] ?? null)) {
            $tipo['nombre'] = trim($tipo['nombre']);
        }
        // Sin vacíos ni repetidos: cada punto de revisión es una casilla.
        if (is_array($tipo['checklist'] ?? null)) {
            $tipo['checklist'] = collect($tipo['checklist'])
                ->filter(fn ($p) => is_string($p) && trim($p) !== '')
                ->map(fn ($p) => trim($p))
                ->unique(fn ($p) => mb_strtolower($p))
                ->values()
                ->all();
        }

        $this->merge(['tipo' => $tipo]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo.nombre' => ['required', 'string', 'max:60', $this->nombreUnico($this->route('tipos_equipo'))],
            'tipo.checklist' => ['present', 'array', 'max:30'],
            'tipo.checklist.*' => ['string', 'max:40'],
            'tipo.activo' => ['required', 'boolean'],
        ];
    }

    /**
     * "Laptop" y "LAPTOP" son el mismo tipo.
     */
    private function nombreUnico(?TipoEquipo $ignorar): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignorar) {
            $existe = TipoEquipo::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $value)])
                ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar->getKey()))
                ->exists();

            if ($existe) {
                $fail('Ya existe un tipo de equipo con ese nombre.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo.nombre' => 'nombre',
            'tipo.checklist' => 'lista de revisión',
            'tipo.checklist.*' => 'punto de revisión',
            'tipo.activo' => 'activo',
        ];
    }
}
