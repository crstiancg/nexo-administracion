<?php

namespace App\Http\Requests;

use App\Support\Cantidades;
use App\Support\Fechas;
use App\Support\Sedes;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y edita una orden de compra PENDIENTE con sus ítems. Payload anidado
 * en `orden`. El total no viene: lo calcula App\Services\OrdenesCompra. El
 * costo por ítem es opcional (el precio del proveedor puede no saberse).
 */
class StoreOrdenCompraRequest extends FormRequest
{
    public const MAX_ITEMS = 200;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $orden = $this->input('orden');
        if (! is_array($orden)) {
            return;
        }

        foreach (['condiciones', 'observacion'] as $campo) {
            if (is_string($orden[$campo] ?? null)) {
                $orden[$campo] = trim($orden[$campo]) ?: null;
            }
        }
        if (($orden['fecha_entrega'] ?? null) === '') {
            $orden['fecha_entrega'] = null;
        }

        $this->merge(['orden' => $orden]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'orden.proveedor_id' => ['required', 'integer', Rule::exists('proveedores', 'id')->where('activo', true)],
            'orden.fecha_entrega' => ['nullable', 'date', 'after_or_equal:'.Fechas::hoyIso()],
            'orden.condiciones' => ['nullable', 'string', 'max:500'],
            'orden.observacion' => ['nullable', 'string', 'max:500'],
            'orden.items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
        ];

        foreach (array_keys((array) $this->input('orden.items', [])) as $i) {
            $varianteId = $this->input("orden.items.{$i}.variante_id");
            $rules["orden.items.{$i}.variante_id"] = [
                'required', 'integer', 'exists:variantes,id', $this->varianteUnica($i),
                Sedes::habilitada($varianteId, $this->user()?->sede_id),
            ];
            $rules["orden.items.{$i}.cantidad"] = [...Cantidades::positiva(), Cantidades::segunUnidad($varianteId)];
            $rules["orden.items.{$i}.costo_unitario"] = ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
        }

        return $rules;
    }

    private function varianteUnica(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('orden.items', []) as $i => $otro) {
                if ($i === $indice) {
                    return;
                }
                if (($otro['variante_id'] ?? null) == $value) {
                    $fail('Esta presentación ya está en otro ítem: sumá la cantidad ahí.');

                    return;
                }
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orden.items.required' => 'Agregá al menos un producto.',
            'orden.items.min' => 'Agregá al menos un producto.',
            'orden.fecha_entrega.after_or_equal' => 'La entrega no puede ser una fecha pasada.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'orden.proveedor_id' => 'proveedor',
            'orden.fecha_entrega' => 'fecha de entrega',
            'orden.condiciones' => 'condiciones',
            'orden.observacion' => 'observación',
            'orden.items.*.variante_id' => 'presentación',
            'orden.items.*.cantidad' => 'cantidad',
            'orden.items.*.costo_unitario' => 'costo',
        ];
    }
}
