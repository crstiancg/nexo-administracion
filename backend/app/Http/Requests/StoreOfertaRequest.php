<?php

namespace App\Http\Requests;

use App\Models\Oferta;
use App\Models\Producto;
use App\Models\Variante;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y edita ofertas. Payload anidado en `oferta`:
 *
 * - alcance "productos": `productos` = [{ producto_id, variantes: [ids] }].
 *   `variantes` vacío = el producto completo; con ids = sólo esas variantes
 *   (el Sikaflex gris y el blanco, por ejemplo).
 * - alcance "categorias": `categorias` = [ids] (+ incluye_subcategorias).
 *
 * Las fechas llegan en ISO 8601 CON zona horaria ("2026-12-15T23:59:00-05:00"):
 * así la oferta termina a las 23:59 de Lima y no de UTC.
 */
class StoreOfertaRequest extends FormRequest
{
    public const MAX_DESTINOS = 200;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $oferta = $this->input('oferta');
        if (! is_array($oferta)) {
            return;
        }

        if (is_string($oferta['nombre'] ?? null)) {
            $oferta['nombre'] = trim($oferta['nombre']);
        }

        // El alcance que no se eligió no viaja.
        if (($oferta['alcance'] ?? null) === 'productos') {
            $oferta['categorias'] = [];
        } elseif (($oferta['alcance'] ?? null) === 'categorias') {
            $oferta['productos'] = [];
        }

        $this->merge(['oferta' => $oferta]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $alcance = $this->input('oferta.alcance');
        $tipo = $this->input('oferta.tipo');

        $rules = [
            'oferta.nombre' => ['required', 'string', 'max:80'],
            'oferta.alcance' => ['required', Rule::in(['productos', 'categorias'])],
            'oferta.productos' => [Rule::requiredIf($alcance === 'productos'), 'array', 'max:'.self::MAX_DESTINOS],
            'oferta.categorias' => [Rule::requiredIf($alcance === 'categorias'), 'array', 'max:'.self::MAX_DESTINOS],
            'oferta.categorias.*' => ['integer', 'distinct', 'exists:categorias,id'],
            'oferta.incluye_subcategorias' => ['boolean'],
            // Un precio fijo para categorías enteras no tiene sentido (sus
            // productos valen distinto): para categorías, sólo porcentaje.
            'oferta.tipo' => [
                'required',
                Rule::in($alcance === 'categorias' ? [Oferta::PORCENTAJE] : [Oferta::PORCENTAJE, Oferta::PRECIO_FIJO]),
            ],
            'oferta.valor' => $tipo === Oferta::PORCENTAJE
                ? ['required', 'numeric', 'min:1', 'max:90', 'decimal:0,2']
                : ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'oferta.inicia_at' => ['required', 'date'],
            'oferta.termina_at' => ['required', 'date', 'after:oferta.inicia_at'],
            'oferta.activa' => ['boolean'],
        ];

        foreach (array_keys((array) $this->input('oferta.productos', [])) as $i) {
            $rules["oferta.productos.{$i}.producto_id"] = [
                'required', 'integer', 'exists:productos,id', $this->productoUnico($i),
                ...($tipo === Oferta::PRECIO_FIJO ? [$this->precioFijoMenor($i)] : []),
            ];
            $rules["oferta.productos.{$i}.variantes"] = ['array'];
            $rules["oferta.productos.{$i}.variantes.*"] = ['integer', 'distinct', $this->varianteDelProducto($i)];
        }

        return $rules;
    }

    private function productoUnico(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('oferta.productos', []) as $i => $otro) {
                if ($i === $indice) {
                    return;
                }
                if (($otro['producto_id'] ?? null) == $value) {
                    $fail('Este producto ya está en la oferta.');

                    return;
                }
            }
        };
    }

    private function varianteDelProducto(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            $productoId = $this->input("oferta.productos.{$indice}.producto_id");
            $es = Variante::query()->whereKey($value)->where('producto_id', $productoId)->exists();

            if (! $es) {
                $fail('Esa variante no es de este producto.');
            }
        };
    }

    /**
     * Un "precio de oferta" más caro que el de lista no es una oferta: el
     * fijo tiene que bajar el precio de CADA producto (o variante elegida).
     */
    private function precioFijoMenor(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            $fijo = (float) $this->input('oferta.valor');
            $producto = Producto::query()->find($value);
            if (! $producto || $fijo <= 0) {
                return;
            }

            $ids = (array) $this->input("oferta.productos.{$indice}.variantes", []);
            $listas = $producto->variantes()
                ->when($ids, fn ($q) => $q->whereKey($ids))
                ->pluck('precio')
                ->map(fn ($p) => (float) ($p ?? $producto->precio));
            $minimo = $listas->isEmpty() ? (float) $producto->precio : $listas->min();

            if ($fijo >= $minimo) {
                $fail("S/ ".number_format($fijo, 2)." no es menor al precio de {$producto->nombre} (S/ ".number_format($minimo, 2).').');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'oferta.tipo.in' => 'Para categorías, la oferta es un porcentaje.',
            'oferta.valor.max' => 'El descuento puede ser de hasta 90%.',
            'oferta.termina_at.after' => 'Tiene que terminar después de empezar.',
            'oferta.productos.required' => 'Agregá al menos un producto.',
            'oferta.categorias.required' => 'Elegí al menos una categoría.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'oferta.nombre' => 'nombre',
            'oferta.valor' => 'descuento',
            'oferta.inicia_at' => 'inicio',
            'oferta.termina_at' => 'fin',
        ];
    }
}
