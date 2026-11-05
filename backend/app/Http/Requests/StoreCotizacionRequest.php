<?php

namespace App\Http\Requests;

use App\Models\Archivo;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\Variante;
use App\Support\Cantidades;
use App\Support\Fechas;
use App\Support\Sedes;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y edita una cotización PENDIENTE con sus ítems. Payload anidado en
 * `cotizacion`. Los totales no vienen: los calcula App\Services\Cotizaciones.
 * El stock no se valida: una cotización no lo reserva.
 *
 * Cada ítem es del catálogo (`variante_id`) o libre (`descripcion`: lo que la
 * tienda no tiene). Su foto opcional va en `archivos` como en productos:
 * `{id}` (ya guardada) o `{archivo}` (nueva). Con una nueva viaja en
 * multipart, y el update como POST con `_method=PUT`.
 */
class StoreCotizacionRequest extends FormRequest
{
    public const MAX_ITEMS = 200;

    public const DIAS_VALIDEZ = 7;

    public const MAX_FOTOS = 1;

    /** @var int[]|null */
    private ?array $archivosPermitidos = null;

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

        foreach (['forma_pago', 'entrega', 'garantia', 'condiciones', 'observacion'] as $campo) {
            if (is_string($cotizacion[$campo] ?? null)) {
                $cotizacion[$campo] = trim($cotizacion[$campo]) ?: null;
            }
        }
        // Sin validez: vale DIAS_VALIDEZ días desde hoy.
        if (in_array($cotizacion['valida_hasta'] ?? null, [null, ''], true)) {
            $cotizacion['valida_hasta'] = Fechas::hoy()->addDays(self::DIAS_VALIDEZ)->toDateString();
        }
        $cotizacion['tipo'] ??= 'normal';
        // Sólo una PC armada se cotiza por N equipos.
        if ($cotizacion['tipo'] !== 'pc' || in_array($cotizacion['equipos'] ?? null, [null, ''], true)) {
            $cotizacion['equipos'] = 1;
        }
        $cotizacion['mostrar_precios'] ??= true;
        // En multipart un booleano llega como texto.
        if (in_array($cotizacion['mostrar_precios'], ['true', 'false'], true)) {
            $cotizacion['mostrar_precios'] = $cotizacion['mostrar_precios'] === 'true';
        }
        if (in_array($cotizacion['descuento'] ?? null, [null, ''], true)) {
            $cotizacion['descuento'] = 0;
        }

        // Descripción del ítem libre sin espacios de más ("" = sin describir).
        foreach ((array) ($cotizacion['items'] ?? []) as $i => $item) {
            if (is_array($item) && is_string($item['descripcion'] ?? null)) {
                $cotizacion['items'][$i]['descripcion'] = trim($item['descripcion']) ?: null;
            }
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
            'cotizacion.forma_pago' => ['nullable', 'string', 'max:100'],
            'cotizacion.entrega' => ['nullable', 'string', 'max:250'],
            'cotizacion.garantia' => ['nullable', 'string', 'max:100'],
            'cotizacion.condiciones' => ['nullable', 'string', 'max:500'],
            'cotizacion.observacion' => ['nullable', 'string', 'max:500'],
            'cotizacion.tipo' => ['required', Rule::in(Cotizacion::TIPOS)],
            'cotizacion.mostrar_precios' => ['boolean'],
            'cotizacion.equipos' => ['integer', 'min:1', 'max:'.Cotizacion::MAX_EQUIPOS],
            'cotizacion.descuento' => ['numeric', 'min:0', 'max:99999999.99', 'decimal:0,2', $this->descuentoNoMayorAlSubtotal()],
            'cotizacion.items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
        ];

        foreach (array_keys((array) $this->input('cotizacion.items', [])) as $i) {
            $varianteId = $this->input("cotizacion.items.{$i}.variante_id");
            // Del catálogo (variante) o libre: lo que la tienda no tiene, descrito a mano.
            $rules["cotizacion.items.{$i}.variante_id"] = [
                "required_without:cotizacion.items.{$i}.descripcion", 'nullable', 'integer', 'exists:variantes,id',
                $this->varianteUnica($i), $this->productoActivo(),
                Sedes::habilitada($varianteId, $this->user()?->sede_id),
            ];
            $rules["cotizacion.items.{$i}.descripcion"] = ['nullable', 'string', 'max:200'];
            // En una PC armada: el casillero ("Procesador", "Memoria RAM"…).
            $rules["cotizacion.items.{$i}.componente"] = ['nullable', 'string', 'max:40'];
            $rules["cotizacion.items.{$i}.unidad"] = ['nullable', 'string', 'max:20'];
            $rules["cotizacion.items.{$i}.cantidad"] = [...Cantidades::positiva(), Cantidades::segunUnidad($varianteId)];
            $rules["cotizacion.items.{$i}.precio_unitario"] = ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
            $rules["cotizacion.items.{$i}.archivos"] = ['nullable', 'array', 'max:'.self::MAX_FOTOS];
            // all() y no input(): input() no trae los archivos subidos.
            foreach (array_keys((array) data_get($this->all(), "cotizacion.items.{$i}.archivos", [])) as $j) {
                $ruta = "cotizacion.items.{$i}.archivos.{$j}";
                $rules["{$ruta}.id"] = ['nullable', 'integer', $this->archivoDeLaCotizacion()];
                $rules["{$ruta}.archivo"] = [
                    "required_without:{$ruta}.id",
                    'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096',
                ];
            }
        }

        return $rules;
    }

    /** Una foto guardada sólo se conserva en la MISMA cotización. */
    private function archivoDeLaCotizacion(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $cotizacion = $this->route('cotizacion');
            $this->archivosPermitidos ??= $cotizacion
                ? Archivo::query()
                    ->where('archivable_type', (new CotizacionItem)->getMorphClass())
                    ->whereIn('archivable_id', $cotizacion->items()->select('id'))
                    ->pluck('id')->map(fn ($id) => (int) $id)->all()
                : [];

            if (! in_array((int) $value, $this->archivosPermitidos, true)) {
                $fail('La foto no pertenece a esta cotización.');
            }
        };
    }

    private function varianteUnica(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('cotizacion.items', []) as $i => $otro) {
                if ($i === $indice) {
                    return;
                }
                if (($otro['variante_id'] ?? null) == $value) {
                    $fail('Esta presentación ya está en otro ítem: suma la cantidad ahí.');

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
            $equipos = max(1, (int) $this->input('cotizacion.equipos', 1));

            if ((float) $value > round($subtotal * $equipos, 2)) {
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
            'cotizacion.items.required' => 'Agrega al menos un producto.',
            'cotizacion.items.min' => 'Agrega al menos un producto.',
            'cotizacion.items.*.variante_id.required_without' => 'Elige un producto o describe lo que ofreces.',
            'cotizacion.items.*.archivos.max' => 'Cada ítem lleva una sola foto.',
            'cotizacion.valida_hasta.after_or_equal' => 'La validez no puede ser una fecha pasada.',
            'cotizacion.valida_hasta.required' => 'Pon hasta cuándo vale la cotización.',
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
            'cotizacion.forma_pago' => 'forma de pago',
            'cotizacion.entrega' => 'entrega',
            'cotizacion.garantia' => 'garantía',
            'cotizacion.condiciones' => 'condiciones',
            'cotizacion.observacion' => 'observación',
            'cotizacion.descuento' => 'descuento',
            'cotizacion.tipo' => 'tipo',
            'cotizacion.mostrar_precios' => 'mostrar precios',
            'cotizacion.equipos' => 'N° de equipos',
            'cotizacion.items.*.componente' => 'componente',
            'cotizacion.items.*.variante_id' => 'presentación',
            'cotizacion.items.*.descripcion' => 'descripción',
            'cotizacion.items.*.unidad' => 'unidad',
            'cotizacion.items.*.archivos' => 'foto',
            'cotizacion.items.*.archivos.*.archivo' => 'foto',
            'cotizacion.items.*.cantidad' => 'cantidad',
            'cotizacion.items.*.precio_unitario' => 'precio',
        ];
    }
}
