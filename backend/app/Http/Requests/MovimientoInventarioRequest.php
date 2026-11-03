<?php

namespace App\Http\Requests;

use App\Models\Variante;
use App\Services\Inventario;
use App\Support\Cantidades;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de entradas, salidas, ajustes y traslados: un documento
 * (`movimiento`) con varias líneas (`movimiento.lineas`), una por
 * presentación. Todo pasa en la sede del usuario (en un traslado, es el
 * origen).
 *
 * Lo que valida acá es la forma. El stock suficiente de una salida también
 * se chequea acá (para avisar mientras se tipea), pero la palabra final la
 * tiene App\Services\Inventario con la fila bloqueada: entre la validación y
 * el guardado otra persona pudo haber descontado.
 */
abstract class MovimientoInventarioRequest extends FormRequest
{
    public const MAX_LINEAS = 200;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas propias de cada línea según el tipo.
     *
     * @return array<string, mixed>
     */
    abstract protected function reglasDeLinea(int|string $i): array;

    /**
     * Reglas del encabezado además de referencia y observación.
     *
     * @return array<string, mixed>
     */
    protected function reglasDelDocumento(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $movimiento = $this->input('movimiento');
        if (! is_array($movimiento)) {
            return;
        }

        foreach (['referencia', 'observacion'] as $campo) {
            if (is_string($movimiento[$campo] ?? null)) {
                $movimiento[$campo] = trim($movimiento[$campo]) ?: null;
            }
        }

        $this->merge(['movimiento' => $movimiento]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'movimiento.referencia' => ['nullable', 'string', 'max:60'],
            'movimiento.observacion' => ['nullable', 'string', 'max:500'],
            'movimiento.lineas' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINEAS],
            ...$this->reglasDelDocumento(),
        ];

        foreach (array_keys((array) $this->input('movimiento.lineas', [])) as $i) {
            $rules["movimiento.lineas.{$i}.variante_id"] = [
                'required', 'integer', 'exists:variantes,id', $this->varianteUnica($i),
            ];
            $rules = [...$rules, ...$this->reglasDeLinea($i)];
        }

        return $rules;
    }

    /**
     * La misma variante dos veces en un documento es un error de carga (¿se
     * sumaban o era la misma línea?): se pide unificarla.
     */
    private function varianteUnica(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('movimiento.lineas', []) as $i => $otra) {
                if ($i === $indice) {
                    return;
                }
                if (($otra['variante_id'] ?? null) == $value) {
                    $fail('Esta presentación ya está en otra línea.');

                    return;
                }
            }
        };
    }

    /**
     * Stock de la presentación de la línea en la sede del usuario (null si
     * todavía no se eligió).
     */
    protected function stockDe(int|string $i): ?float
    {
        return Cantidades::stockEnSede($this->input("movimiento.lineas.{$i}.variante_id"), $this->user()?->sede_id);
    }

    /** @var array<int, bool> */
    private array $manejaLotes = [];

    /**
     * Si el producto de la presentación de la línea maneja lotes.
     */
    protected function manejaLotes(int|string $i): bool
    {
        $id = $this->input("movimiento.lineas.{$i}.variante_id");
        if (! is_numeric($id)) {
            return false;
        }

        return $this->manejaLotes[(int) $id] ??= Variante::query()->whereKey($id)
            ->whereHas('producto', fn ($q) => $q->where('maneja_lotes', true))
            ->exists();
    }

    /**
     * Cantidad positiva y, si la unidad no es fraccionable, entera.
     *
     * @return array<int, mixed>
     */
    protected function reglasCantidad(int|string $i): array
    {
        return [...Cantidades::positiva(), Cantidades::segunUnidad($this->input("movimiento.lineas.{$i}.variante_id"))];
    }

    /**
     * Para salidas y traslados: no sacar más de lo que hay en la sede.
     */
    protected function hayStock(int|string $i): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($i) {
            $stock = $this->stockDe($i);

            if ($stock !== null && (float) $value > $stock + 0.0005) {
                $fail('Stock insuficiente en tu sede: hay '.Inventario::formatear($stock).'.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'movimiento.lineas.required' => 'Agregá al menos una línea.',
            'movimiento.lineas.min' => 'Agregá al menos una línea.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'movimiento.referencia' => 'referencia',
            'movimiento.observacion' => 'observación',
            'movimiento.motivo' => 'motivo',
            'movimiento.lineas.*.variante_id' => 'presentación',
            'movimiento.sede_destino_id' => 'sede de destino',
            'movimiento.lineas.*.cantidad' => 'cantidad',
            'movimiento.lineas.*.costo_unitario' => 'costo unitario',
            'movimiento.lineas.*.stock_real' => 'stock contado',
            'movimiento.lineas.*.lote' => 'lote',
            'movimiento.lineas.*.vence_at' => 'vencimiento',
            'movimiento.lineas.*.lote_id' => 'lote',
        ];
    }
}
