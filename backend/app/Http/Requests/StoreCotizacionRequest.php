<?php

namespace App\Http\Requests;

use App\Models\Variante;
use App\Support\Cantidades;
use App\Support\Fechas;
use App\Support\Sedes;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Crea y edita una cotización PENDIENTE con sus ítems. Payload anidado en
 * `cotizacion`. Los totales no vienen: los calcula App\Services\Cotizaciones.
 * El stock no se valida: una cotización no lo reserva.
 */
class StoreCotizacionRequest extends FormRequest
{
    public const MAX_ITEMS = 200;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $cotizacion = $this->input('cotizacion');
        if (! is_array($cotizacion)) {
            return;
        }

        foreach (['condiciones', 'observacion'] as $campo) {
            if (is_string($cotizacion[$campo] ?? null)) {
                $cotizacion[$campo] = trim($cotizacion[$campo]) ?: null;
            }
        }
        if (in_array($cotizacion['descuento'] ?? null, [null, ''], true)) {
            $cotizacion['descuento'] = 0;
        }

        $this->merge(['cotizacion' => $cotizacion]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            // Opcional: "cotización sin nombre" para quien sólo pide precios.
            'cotizacion.cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'cotizacion.valida_hasta' => ['required', 'date', 'after_or_equal:'.Fechas::hoyIso()],
            'cotizacion.condiciones' => ['nullable', 'string', 'max:500'],
            'cotizacion.observacion' => ['nullable', 'string', 'max:500'],
            'cotizacion.descuento' => ['numeric', 'min:0', 'max:99999999.99', 'decimal:0,2', $this->descuentoNoMayorAlSubtotal()],
            'cotizacion.items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
        ];

        foreach (array_keys((array) $this->input('cotizacion.items', [])) as $i) {
            $varianteId = $this->input("cotizacion.items.{$i}.variante_id");
            $rules["cotizacion.items.{$i}.variante_id"] = [
                'required', 'integer', 'exists:variantes,id', $this->varianteUnica($i), $this->productoActivo(),
                Sedes::habilitada($varianteId, $this->user()?->sede_id),
            ];
            $rules["cotizacion.items.{$i}.cantidad"] = [...Cantidades::positiva(), Cantidades::segunUnidad($varianteId)];
            $rules["cotizacion.items.{$i}.precio_unitario"] = ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
        }

        return $rules;
    }

    private function varianteUnica(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('cotizacion.items', []) as $i => $otro) {
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

    private function productoActivo(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $activo = Variante::query()->whereKey($value)
                ->whereHas('producto', fn ($q) => $q->where('activo', true))
                ->exists();

            if (! $activo) {
                $fail('El producto de esta presentación está desactivado.');
            }
        };
    }

    private function descuentoNoMayorAlSubtotal(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $subtotal = collect((array) $this->input('cotizacion.items', []))
                ->sum(fn ($i) => round((float) ($i['cantidad'] ?? 0) * (float) ($i['precio_unitario'] ?? 0), 2));

            if ((float) $value > round($subtotal, 2)) {
                $fail('El descuento no puede ser mayor que el subtotal.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cotizacion.items.required' => 'Agregá al menos un producto.',
            'cotizacion.items.min' => 'Agregá al menos un producto.',
            'cotizacion.valida_hasta.after_or_equal' => 'La validez no puede ser una fecha pasada.',
            'cotizacion.valida_hasta.required' => 'Poné hasta cuándo vale la cotización.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cotizacion.cliente_id' => 'cliente',
            'cotizacion.valida_hasta' => 'válida hasta',
            'cotizacion.condiciones' => 'condiciones',
            'cotizacion.observacion' => 'observación',
            'cotizacion.descuento' => 'descuento',
            'cotizacion.items.*.variante_id' => 'presentación',
            'cotizacion.items.*.cantidad' => 'cantidad',
            'cotizacion.items.*.precio_unitario' => 'precio',
        ];
    }
}
