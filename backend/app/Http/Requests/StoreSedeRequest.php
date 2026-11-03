<?php

namespace App\Http\Requests;

use App\Models\Sede;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Crea y actualiza sedes. Payload anidado en `sede`.
 */
class StoreSedeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sede = $this->input('sede');
        if (! is_array($sede)) {
            return;
        }

        foreach (['nombre', 'direccion', 'telefono', 'codigo_sunat'] as $campo) {
            if (is_string($sede[$campo] ?? null)) {
                $sede[$campo] = trim($sede[$campo]) ?: null;
            }
        }

        $this->merge(['sede' => $sede]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sede.nombre' => ['required', 'string', 'max:60', $this->nombreUnico($this->route('sede'))],
            'sede.direccion' => ['nullable', 'string', 'max:200'],
            'sede.telefono' => ['nullable', 'string', 'max:30'],
            // Código del establecimiento en la ficha RUC (0000 = domicilio fiscal).
            'sede.codigo_sunat' => ['required', 'regex:/^\d{4}$/'],
            'sede.activo' => ['required', 'boolean', $this->noDesactivaConStock($this->route('sede'))],
        ];
    }

    private function nombreUnico(?Sede $ignorar): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignorar) {
            $existe = Sede::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $value)])
                ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar->getKey()))
                ->exists();

            if ($existe) {
                $fail('Ya existe una sede con ese nombre.');
            }
        };
    }

    /**
     * Una sede inactiva no recibe traslados ni vende: si todavía tiene
     * mercadería, quedaría atrapada ahí.
     */
    private function noDesactivaConStock(?Sede $sede): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($sede) {
            if ($sede && ! $value && $sede->stocks()->where('cantidad', '>', 0)->exists()) {
                $fail('La sede todavía tiene stock: trasladalo a otra sede antes de desactivarla.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sede.nombre' => 'nombre',
            'sede.direccion' => 'dirección',
            'sede.telefono' => 'teléfono',
            'sede.codigo_sunat' => 'código de establecimiento SUNAT',
            'sede.activo' => 'activo',
        ];
    }
}
