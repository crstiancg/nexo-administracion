<?php

namespace App\Http\Requests;

use App\Models\Compra;
use App\Models\OrdenCompra;
use App\Models\Variante;
use App\Support\Cantidades;
use App\Support\Fechas;
use App\Support\Sedes;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registra una compra (entra al inventario de la sede del usuario). Payload
 * anidado en `compra`, ítems en `compra.items`. El total no viene: lo
 * calcula App\Services\Compras.
 */
class StoreCompraRequest extends FormRequest
{
    public const MAX_ITEMS = 200;

    /** @var array<int, bool> */
    private array $manejaLotes = [];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $compra = $this->input('compra');
        if (! is_array($compra)) {
            return;
        }

        foreach (['numero_documento', 'observacion'] as $campo) {
            if (is_string($compra[$campo] ?? null)) {
                $compra[$campo] = trim($compra[$campo]) ?: null;
            }
        }
        if (is_string($compra['numero_documento'] ?? null)) {
            $compra['numero_documento'] = mb_strtoupper($compra['numero_documento']);
        }

        $this->merge(['compra' => $compra]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'compra.proveedor_id' => ['required', 'integer', Rule::exists('proveedores', 'id')->where('activo', true)],
            'compra.tipo_documento' => ['required', Rule::in(array_keys(Compra::TIPOS_DOCUMENTO))],
            // Una factura o boleta sin número no se puede rastrear.
            'compra.numero_documento' => [
                Rule::requiredIf(fn () => in_array($this->input('compra.tipo_documento'), ['factura', 'boleta'], true)),
                'nullable', 'string', 'max:30', $this->documentoNoRepetido(),
            ],
            'compra.fecha' => ['required', 'date', 'before_or_equal:today'],
            'compra.observacion' => ['nullable', 'string', 'max:500'],
            // La orden de compra que se recibe con esta compra (opcional): del
            // mismo proveedor y todavía abierta.
            'compra.orden_compra_id' => [
                'nullable', 'integer',
                Rule::exists('ordenes_compra', 'id')
                    ->whereIn('estado', [OrdenCompra::PENDIENTE, OrdenCompra::ENVIADA])
                    ->where('proveedor_id', (int) $this->input('compra.proveedor_id')),
            ],
            'compra.items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
        ];

        foreach (array_keys((array) $this->input('compra.items', [])) as $i) {
            $varianteId = $this->input("compra.items.{$i}.variante_id");
            $rules["compra.items.{$i}.variante_id"] = [
                'required', 'integer', 'exists:variantes,id', $this->varianteUnica($i),
                Sedes::habilitada($varianteId, $this->user()?->sede_id),
            ];
            $rules["compra.items.{$i}.cantidad"] = [...Cantidades::positiva(), Cantidades::segunUnidad($varianteId)];
            $rules["compra.items.{$i}.costo_unitario"] = ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
            $rules["compra.items.{$i}.lote"] = [Rule::requiredIf(fn () => $this->manejaLotes($varianteId)), 'nullable', 'string', 'max:40'];
            $rules["compra.items.{$i}.vence_at"] = [Rule::requiredIf(fn () => $this->manejaLotes($varianteId)), 'nullable', 'date', 'after_or_equal:'.Fechas::hoyIso()];
        }

        return $rules;
    }

    private function manejaLotes(mixed $varianteId): bool
    {
        if (! is_numeric($varianteId)) {
            return false;
        }

        return $this->manejaLotes[(int) $varianteId] ??= Variante::query()->whereKey($varianteId)
            ->whereHas('producto', fn ($q) => $q->where('maneja_lotes', true))
            ->exists();
    }

    /**
     * La misma factura del mismo proveedor cargada dos veces duplicaría el
     * stock (salvo que la primera se haya anulado).
     */
    private function documentoNoRepetido(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $existe = Compra::query()
                ->where('proveedor_id', $this->input('compra.proveedor_id'))
                ->where('tipo_documento', $this->input('compra.tipo_documento'))
                ->where('numero_documento', $value)
                ->where('estado', Compra::REGISTRADA)
                ->value('codigo');

            if ($existe) {
                $fail("Ese documento ya está registrado en la compra {$existe}.");
            }
        };
    }

    private function varianteUnica(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('compra.items', []) as $i => $otro) {
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
            'compra.items.required' => 'Agregá al menos un producto.',
            'compra.items.min' => 'Agregá al menos un producto.',
            'compra.items.*.vence_at.after_or_equal' => 'No se recibe mercadería ya vencida.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'compra.proveedor_id' => 'proveedor',
            'compra.tipo_documento' => 'tipo de documento',
            'compra.numero_documento' => 'número de documento',
            'compra.fecha' => 'fecha',
            'compra.observacion' => 'observación',
            'compra.items.*.variante_id' => 'presentación',
            'compra.items.*.cantidad' => 'cantidad',
            'compra.items.*.costo_unitario' => 'costo',
            'compra.items.*.lote' => 'lote',
            'compra.items.*.vence_at' => 'vencimiento',
        ];
    }
}
