<?php

namespace App\Http\Requests;

use App\Models\Pedido;
use App\Models\Variante;
use App\Support\Cantidades;
use App\Support\Sedes;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y edita un pedido PENDIENTE con sus ítems. Payload anidado en
 * `pedido`. Los totales no vienen: los calcula App\Services\Pedidos.
 *
 * El stock no se valida acá: un pedido pendiente todavía no lo toca. Se
 * exige al confirmar, con la fila bloqueada.
 */
class StorePedidoRequest extends FormRequest
{
    public const MAX_ITEMS = 100;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $pedido = $this->input('pedido');
        if (! is_array($pedido)) {
            return;
        }

        if (is_string($pedido['observacion'] ?? null)) {
            $pedido['observacion'] = trim($pedido['observacion']) ?: null;
        }
        if (($pedido['descuento'] ?? null) === '' || ($pedido['descuento'] ?? null) === null) {
            $pedido['descuento'] = 0;
        }

        $this->merge(['pedido' => $pedido]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'pedido.cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'pedido.canal' => ['required', Rule::in(array_keys(Pedido::CANALES))],
            'pedido.descuento' => ['numeric', 'min:0', 'max:99999999.99', 'decimal:0,2', $this->descuentoNoMayorAlSubtotal()],
            'pedido.observacion' => ['nullable', 'string', 'max:500'],
            'pedido.items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
        ];

        foreach (array_keys((array) $this->input('pedido.items', [])) as $i) {
            $rules["pedido.items.{$i}.variante_id"] = [
                'required', 'integer', 'exists:variantes,id', $this->varianteUnica($i), $this->productoActivo(),
                Sedes::habilitada($this->input("pedido.items.{$i}.variante_id"), $this->user()?->sede_id),
            ];
            $rules["pedido.items.{$i}.cantidad"] = [...Cantidades::positiva(10000), Cantidades::segunUnidad($this->input("pedido.items.{$i}.variante_id"))];
            $rules["pedido.items.{$i}.precio_unitario"] = ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
        }

        return $rules;
    }

    private function varianteUnica(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('pedido.items', []) as $i => $otro) {
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
            $subtotal = collect((array) $this->input('pedido.items', []))
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
            'pedido.items.required' => 'Agregá al menos un producto.',
            'pedido.items.min' => 'Agregá al menos un producto.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pedido.cliente_id' => 'cliente',
            'pedido.canal' => 'canal',
            'pedido.descuento' => 'descuento',
            'pedido.observacion' => 'observación',
            'pedido.items.*.variante_id' => 'presentación',
            'pedido.items.*.cantidad' => 'cantidad',
            'pedido.items.*.precio_unitario' => 'precio',
        ];
    }
}
