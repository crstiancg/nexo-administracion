<?php

namespace App\Http\Requests;

use App\Models\Color;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y actualiza colores. Payload anidado en `color`.
 */
class StoreColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El hexadecimal se guarda siempre como "#RRGGBB" en mayúsculas: "ff0000",
     * "#F00" y "#ff0000" son el mismo color y el unique tiene que verlo así.
     */
    protected function prepareForValidation(): void
    {
        $color = $this->input('color');
        if (! is_array($color)) {
            return;
        }

        if (is_string($color['nombre'] ?? null)) {
            $color['nombre'] = trim($color['nombre']);
        }

        if (is_string($color['hexadecimal'] ?? null)) {
            $color['hexadecimal'] = self::normalizarHex($color['hexadecimal']);
        }

        $this->merge(['color' => $color]);
    }

    public static function normalizarHex(string $valor): string
    {
        $hex = strtoupper(ltrim(trim($valor), '#'));

        // Formato corto: "F0A" → "FF00AA".
        if (preg_match('/^[0-9A-F]{3}$/', $hex)) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }

        return "#{$hex}";
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $color = $this->route('color');

        return [
            'color.nombre' => ['required', 'string', 'max:60', $this->nombreUnico($color)],
            'color.hexadecimal' => [
                'required', 'regex:/^#[0-9A-F]{6}$/',
                Rule::unique('colores', 'hexadecimal')->ignore($color),
            ],
        ];
    }

    /**
     * "Rojo" y "rojo" son el mismo nombre. MySQL ya compara sin distinguir
     * mayúsculas, SQLite no: se chequea explícito para que valga en ambos.
     */
    private function nombreUnico(?Color $ignorar): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignorar) {
            $existe = Color::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $value)])
                ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar->getKey()))
                ->exists();

            if ($existe) {
                $fail('Ya existe un color con ese nombre.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color.hexadecimal.regex' => 'El hexadecimal tiene que tener el formato #RRGGBB (por ejemplo #E30613).',
            'color.hexadecimal.unique' => 'Ya existe un color con ese hexadecimal.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'color.nombre' => 'nombre',
            'color.hexadecimal' => 'hexadecimal',
        ];
    }
}
