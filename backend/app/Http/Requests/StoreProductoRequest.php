<?php

namespace App\Http\Requests;

use App\Models\Archivo;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Variante;
use App\Support\Ean13;
use App\Support\Fechas;
use App\Support\HtmlSeguro;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y actualiza un producto junto con sus presentaciones (variantes:
 * "Balde 4 gl", "Cartucho 300 ml gris"...).
 * Payload anidado en `producto`; las variantes en `producto.variantes`.
 *
 * El stock NO viene en el payload: sólo lo mueve el inventario.
 *
 * Viaja como multipart cuando hay fotos nuevas (el update, como POST con
 * _method=PUT: PHP no parsea multipart en un PUT). Fotos del producto en
 * `producto.archivos` y de cada variante en `producto.variantes.N.archivos`;
 * cada ítem es `{id}` (una foto ya guardada) o `{archivo}` (una nueva).
 */
class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $producto = $this->input('producto');
        if (! is_array($producto)) {
            return;
        }

        if (is_string($producto['nombre'] ?? null)) {
            $producto['nombre'] = trim($producto['nombre']);
        }

        // Viene del editor enriquecido: se guarda limpia (lista blanca).
        if (array_key_exists('descripcion', $producto)) {
            $producto['descripcion'] = is_string($producto['descripcion']) ? HtmlSeguro::limpiar($producto['descripcion']) : null;
        }

        // En multipart un booleano llega como texto.
        foreach (['activo', 'maneja_lotes'] as $campo) {
            if (in_array($producto[$campo] ?? null, ['true', 'false'], true)) {
                $producto[$campo] = $producto[$campo] === 'true';
            }
        }

        if (is_array($producto['variantes'] ?? null)) {
            $producto['variantes'] = array_map(function ($variante) {
                if (! is_array($variante)) {
                    return $variante;
                }
                if (is_string($variante['sku'] ?? null)) {
                    $variante['sku'] = mb_strtoupper(trim($variante['sku']));
                }
                if (is_string($variante['presentacion'] ?? null)) {
                    $variante['presentacion'] = trim($variante['presentacion']);
                }
                // Sin espacios ni guiones; un UPC-A de 12 dígitos pasa a EAN-13.
                if (is_string($variante['codigo_barras'] ?? null)) {
                    $variante['codigo_barras'] = Ean13::normalizar($variante['codigo_barras']);
                }
                // Sin color = presentación sin color (la mayoría).
                if (($variante['color_id'] ?? null) === '') {
                    $variante['color_id'] = null;
                }
                // Por sede: vacío = sin precio propio / sin mínimo. En
                // multipart el activo llega como texto.
                if (is_array($variante['sedes'] ?? null)) {
                    $variante['sedes'] = array_map(function ($sede) {
                        if (! is_array($sede)) {
                            return $sede;
                        }
                        if (in_array($sede['activo'] ?? null, ['true', 'false', '1', '0'], true)) {
                            $sede['activo'] = in_array($sede['activo'], ['true', '1'], true);
                        }
                        if (($sede['precio'] ?? null) === '') {
                            $sede['precio'] = null;
                        }
                        if (in_array($sede['stock_minimo'] ?? null, [null, ''], true)) {
                            $sede['stock_minimo'] = 0;
                        }

                        return $sede;
                    }, $variante['sedes']);
                }
                // Precio vacío = usa el precio base del producto.
                if (($variante['precio'] ?? null) === '') {
                    $variante['precio'] = null;
                }
                // Precio por mayor vacío = no tiene (al mayorista, el normal).
                if (($variante['precio_mayor'] ?? null) === '') {
                    $variante['precio_mayor'] = null;
                }

                return $variante;
            }, $producto['variantes']);
        }

        $this->merge(['producto' => $producto]);
    }

    /**
     * Las reglas de cada variante se arman por índice: el unique del SKU
     * tiene que ignorar el id de ESA fila, no uno global.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $producto = $this->route('producto');

        $rules = [
            'producto.nombre' => ['required', 'string', 'max:120', $this->nombreUnico($producto)],
            'producto.categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'producto.marca_id' => ['required', 'integer', 'exists:marcas,id'],
            // HTML: el límite cuenta las etiquetas, por eso es más que el texto visible.
            'producto.descripcion' => ['nullable', 'string', 'max:5000'],
            'producto.precio' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'producto.activo' => ['required', 'boolean'],
            'producto.maneja_lotes' => ['required', 'boolean', $this->lotesSinStock($producto)],
            'producto.variantes' => ['required', 'array', 'min:1', $this->noQuitaVariantesConStock($producto)],
            // Stock inicial de las variantes nuevas: el costo general de la
            // compra (ajustable por variante) y la factura o guía.
            // Obligatorio (y > 0) si hay stock inicial sin costo propio: sin
            // costo, toda la venta contaría como ganancia.
            'producto.costo_compra' => [Rule::requiredIf(fn () => $this->faltaCostoGeneral()), 'nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2', $this->costoCompraValido()],
            'producto.referencia_compra' => ['nullable', 'string', 'max:60'],
            ...$this->reglasArchivos('producto.archivos'),
        ];

        foreach (array_keys((array) $this->input('producto.variantes', [])) as $i) {
            $rules["producto.variantes.{$i}.id"] = [
                'nullable', 'integer',
                // Sólo se editan variantes de ESTE producto (al crear, ninguna).
                Rule::exists('variantes', 'id')->where('producto_id', $producto?->getKey() ?? 0),
            ];
            $rules["producto.variantes.{$i}.presentacion"] = ['required', 'string', 'max:60', $this->combinacionUnica($i)];
            $rules["producto.variantes.{$i}.unidad_id"] = ['required', 'integer', 'exists:unidades,id'];
            $rules["producto.variantes.{$i}.color_id"] = ['nullable', 'integer', 'exists:colores,id'];
            // Una fila por sede: si la vende, su precio (null = el general) y
            // su mínimo. Sin filas = no cambia nada de lo que ya tenía.
            $rules["producto.variantes.{$i}.sedes"] = ['nullable', 'array', $this->sedesSinRepetir(), $this->noDeshabilitaConStock($i)];
            $rules["producto.variantes.{$i}.sedes.*.sede_id"] = ['required', 'integer', 'exists:sedes,id'];
            $rules["producto.variantes.{$i}.sedes.*.activo"] = ['required', 'boolean'];
            $rules["producto.variantes.{$i}.sedes.*.precio"] = ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
            $rules["producto.variantes.{$i}.sedes.*.stock_minimo"] = ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,3'];
            $rules["producto.variantes.{$i}.sku"] = [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9-]+$/',
                $this->skuUnicoEnElProducto($i),
                // Contra OTROS productos: dentro de éste ya lo cubre la regla
                // anterior, y así se pueden intercambiar SKUs entre variantes
                // (o reusar el de una que se quita) en el mismo guardado.
                Rule::unique('variantes', 'sku')->where(fn ($q) => $q->where('producto_id', '!=', $producto?->getKey() ?? 0)),
            ];
            // El que trae el envase (o uno propio): sólo obligatorio, sin reglas
            // de formato ni de largo. Único: si no, la lectora del POS no
            // sabría qué producto agregar. (255 = el tope de la columna.)
            $rules["producto.variantes.{$i}.codigo_barras"] = [
                'required', 'string', 'max:255',
                $this->codigoUnicoEnElProducto($i),
                Rule::unique('variantes', 'codigo_barras')->where(fn ($q) => $q->where('producto_id', '!=', $producto?->getKey() ?? 0)),
            ];
            $rules["producto.variantes.{$i}.precio"] = ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
            $rules["producto.variantes.{$i}.precio_mayor"] = ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
            $rules["producto.variantes.{$i}.stock_inicial"] = ['nullable', 'numeric', 'min:0', 'max:100000', 'decimal:0,3', $this->stockInicialValido($i)];
            $rules["producto.variantes.{$i}.costo_unitario"] = ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
            // Con lotes, el stock inicial entra a un lote (igual que una entrada).
            $conLote = Rule::requiredIf(fn () => $this->stockInicialConLote($i));
            $rules["producto.variantes.{$i}.lote"] = [$conLote, 'nullable', 'string', 'max:40'];
            $rules["producto.variantes.{$i}.vence_at"] = [$conLote, 'nullable', 'date', 'after_or_equal:'.Fechas::hoyIso()];
            $rules = [...$rules, ...$this->reglasArchivos("producto.variantes.{$i}.archivos")];
        }

        return $rules;
    }

    public const MAX_ARCHIVOS = 6;

    /** @var int[]|null */
    private ?array $archivosPermitidos = null;

    /**
     * Por índice, igual que las variantes: cada ítem es una foto ya guardada
     * (`id`) o una nueva (`archivo`).
     *
     * @return array<string, mixed>
     */
    private function reglasArchivos(string $ruta): array
    {
        $rules = [$ruta => ['nullable', 'array', 'max:'.self::MAX_ARCHIVOS]];

        // all() y no input(): input() no trae los archivos subidos, y un ítem
        // que es sólo {archivo} se quedaría sin reglas (y sin validar).
        foreach (array_keys((array) data_get($this->all(), $ruta, [])) as $j) {
            $rules["{$ruta}.{$j}.id"] = ['nullable', 'integer', $this->archivoDelProducto()];
            $rules["{$ruta}.{$j}.archivo"] = [
                "required_without:{$ruta}.{$j}.id",
                'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:4096',
            ];
        }

        return $rules;
    }

    /**
     * Una foto existente se puede reusar en cualquier parte del MISMO
     * producto (así se copia a otras presentaciones), nunca una
     * de otro producto.
     */
    private function archivoDelProducto(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $this->archivosPermitidos ??= $this->idsDeArchivosDelProducto();

            if (! in_array((int) $value, $this->archivosPermitidos, true)) {
                $fail('La foto no pertenece a este producto.');
            }
        };
    }

    /**
     * @return int[]
     */
    private function idsDeArchivosDelProducto(): array
    {
        $producto = $this->route('producto');
        if (! $producto) {
            return [];
        }

        return Archivo::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q
                    ->where('archivable_type', $producto->getMorphClass())
                    ->where('archivable_id', $producto->getKey()))
                ->orWhere(fn ($q) => $q
                    ->where('archivable_type', (new Variante)->getMorphClass())
                    ->whereIn('archivable_id', $producto->variantes()->select('id'))))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Sólo las variantes nuevas reciben stock desde acá (las existentes se
     * mueven en Inventario), y sin costo no entra: sin costo no hay forma de
     * calcular la ganancia de esas unidades.
     */
    private function stockInicialValido(int|string $i): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($i) {
            if ((float) $value <= 0) {
                return;
            }
            // Va a la sede del usuario: sin sede no hay dónde ponerlo, y la
            // sede tiene que venderla.
            $sedeId = $this->user()?->sede_id;
            if (! $sedeId) {
                $fail('Tu usuario no tiene sede asignada: el stock inicial no tiene dónde entrar.');

                return;
            }
            $enMiSede = collect((array) $this->input("producto.variantes.{$i}.sedes", []))
                ->first(fn ($s) => (int) ($s['sede_id'] ?? 0) === $sedeId);
            if ($enMiSede !== null && ! filter_var($enMiSede['activo'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $fail('El stock inicial entra en tu sede: habilitá la presentación ahí.');

                return;
            }
            if ($this->input("producto.variantes.{$i}.id")) {
                $fail('El stock de una presentación que ya existe se carga desde Inventario.');

                return;
            }

            // Sin costo general, el error ya sale en el campo de costo de compra
            // (costoCompraValido): acá sólo el costo propio de la fila en 0.
            $propio = $this->input("producto.variantes.{$i}.costo_unitario");
            if ($propio !== null && $propio !== '' && (float) $propio <= 0) {
                $fail('El costo de estas unidades tiene que ser mayor a 0.');
            }
        };
    }

    /**
     * Con stock inicial, el costo general es obligatorio salvo que TODAS las
     * presentaciones con stock tengan su costo propio.
     */
    private function faltaCostoGeneral(): bool
    {
        return collect((array) $this->input('producto.variantes', []))
            ->filter(fn ($v) => empty($v['id']) && (float) ($v['stock_inicial'] ?? 0) > 0)
            ->contains(fn ($v) => ($v['costo_unitario'] ?? null) === null || $v['costo_unitario'] === '');
    }

    /**
     * Y si se usa, mayor a 0: con costo 0 toda la venta contaría como ganancia.
     */
    private function costoCompraValido(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($this->faltaCostoGeneral() && (float) $value <= 0) {
                $fail('El costo tiene que ser mayor a 0: con 0, toda la venta contaría como ganancia.');
            }
        };
    }

    /**
     * Presentación nueva de un producto con lotes que entra con stock inicial.
     */
    private function stockInicialConLote(int|string $i): bool
    {
        return filter_var($this->input('producto.maneja_lotes'), FILTER_VALIDATE_BOOLEAN)
            && ! $this->input("producto.variantes.{$i}.id")
            && (float) $this->input("producto.variantes.{$i}.stock_inicial", 0) > 0;
    }

    private function sedesSinRepetir(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $ids = collect((array) $value)->pluck('sede_id')->map(fn ($id) => (int) $id);
            if ($ids->count() !== $ids->unique()->count()) {
                $fail('Una sede aparece dos veces.');
            }
        };
    }

    /**
     * Dejar de vender en una sede donde todavía hay stock lo escondería de
     * su POS con la mercadería adentro: primero se traslada o se saca.
     */
    private function noDeshabilitaConStock(int|string $i): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($i) {
            $id = $this->input("producto.variantes.{$i}.id");
            if (! $id) {
                return;
            }

            $apagadas = collect((array) $value)
                ->filter(fn ($s) => ! filter_var($s['activo'] ?? true, FILTER_VALIDATE_BOOLEAN))
                ->pluck('sede_id');

            $conStock = Stock::query()
                ->with('sede:id,nombre')
                ->where('variante_id', $id)
                ->whereIn('sede_id', $apagadas)
                ->where('cantidad', '>', 0)
                ->get();

            if ($conStock->isNotEmpty()) {
                $fail('Todavía hay stock en '.$conStock->map(fn ($s) => $s->sede->nombre)->implode(', ').': trasladalo o sacalo antes de dejar de venderla ahí.');
            }
        };
    }

    /**
     * Prender o apagar los lotes con stock dejaría unidades sin lote (o
     * lotes que ya no cuadran con el stock): sólo con el producto en 0.
     */
    private function lotesSinStock(?Producto $producto): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($producto) {
            // Prenderlo con stock se puede: ese stock queda "sin lote" y la
            // ficha del producto pide asignárselo. Apagarlo no: dejaría lotes
            // con cantidad colgados de un producto que ya no los usa.
            if ($producto && $producto->maneja_lotes && ! (bool) $value && $producto->variantes()->where('stock', '!=', 0)->exists()) {
                $fail('Sólo se puede desactivar el manejo de lotes con el producto sin stock.');
            }
        };
    }

    private function nombreUnico(?Producto $ignorar): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignorar) {
            $existe = Producto::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $value)])
                ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar->getKey()))
                ->exists();

            if ($existe) {
                $fail('Ya existe un producto con ese nombre.');
            }
        };
    }

    /**
     * La misma presentación (y color) dos veces en el mismo producto es la
     * misma variante. El error va en la fila repetida, no en la primera. Va
     * acá y no sólo en el unique de la tabla: con color null MySQL no lo
     * hace cumplir.
     */
    private function combinacionUnica(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            $variantes = (array) $this->input('producto.variantes', []);
            $color = $variantes[$indice]['color_id'] ?? null;

            foreach ($variantes as $i => $otra) {
                if ($i === $indice) {
                    return;
                }
                if (mb_strtolower((string) ($otra['presentacion'] ?? '')) === mb_strtolower((string) $value)
                    && ($otra['color_id'] ?? null) == $color) {
                    $fail('Esta presentación ya está cargada en este producto.');

                    return;
                }
            }
        };
    }

    /**
     * `distinct` no sirve acá: sólo compara con reglas comodín
     * (`variantes.*.sku`) y éstas van por índice. Mismo criterio que
     * combinacionUnica: el error queda en la fila repetida.
     */
    private function codigoUnicoEnElProducto(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('producto.variantes', []) as $i => $otra) {
                if ($i === $indice) {
                    return;
                }
                if (($otra['codigo_barras'] ?? null) === $value) {
                    $fail('Código de barras repetido en este producto.');

                    return;
                }
            }
        };
    }

    private function skuUnicoEnElProducto(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('producto.variantes', []) as $i => $otra) {
                if ($i === $indice) {
                    return;
                }
                if (($otra['sku'] ?? null) === $value) {
                    $fail('SKU repetido en este producto.');

                    return;
                }
            }
        };
    }

    /**
     * Quitar una variante que tiene stock haría desaparecer mercadería, y una
     * con historial de inventario dejaría movimientos sin su variante.
     */
    private function noQuitaVariantesConStock(?Producto $producto): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($producto) {
            if (! $producto) {
                return;
            }

            $conservadas = collect((array) $value)->pluck('id')->filter()->map(fn ($id) => (int) $id);

            $bloqueadas = $producto->variantes()
                ->whereNotIn('id', $conservadas)
                ->where(fn ($q) => $q->where('stock', '!=', 0)->orWhereHas('movimientos'))
                ->pluck('sku');

            if ($bloqueadas->isNotEmpty()) {
                $fail('No se pueden quitar presentaciones con stock o con historial de inventario: '.$bloqueadas->implode(', ').'.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'producto.variantes.*.codigo_barras.unique' => 'Ese código de barras ya lo tiene otro producto.',
            'producto.costo_compra.required' => 'Poné cuánto te costó cada unidad: sin el costo no se puede calcular la ganancia.',
            'producto.variantes.required' => 'Agregá al menos una presentación.',
            'producto.variantes.min' => 'Agregá al menos una presentación.',
            'producto.variantes.*.sku.regex' => 'El SKU sólo puede tener letras, números y guiones.',
            'producto.variantes.*.sku.unique' => 'Ese SKU ya lo usa otra presentación.',
            'producto.archivos.max' => 'Máximo '.self::MAX_ARCHIVOS.' fotos.',
            'producto.variantes.*.archivos.max' => 'Máximo '.self::MAX_ARCHIVOS.' fotos por presentación.',
            'producto.archivos.*.archivo.max' => 'Cada foto puede pesar hasta 4 MB.',
            'producto.variantes.*.archivos.*.archivo.max' => 'Cada foto puede pesar hasta 4 MB.',
            'producto.archivos.*.archivo.mimes' => 'Sólo fotos JPG, PNG, WEBP o AVIF.',
            'producto.variantes.*.archivos.*.archivo.mimes' => 'Sólo fotos JPG, PNG, WEBP o AVIF.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'producto.nombre' => 'nombre',
            'producto.categoria_id' => 'categoría',
            'producto.marca_id' => 'marca',
            'producto.descripcion' => 'descripción',
            'producto.precio' => 'precio',
            'producto.activo' => 'activo',
            'producto.maneja_lotes' => 'maneja lotes',
            'producto.variantes.*.presentacion' => 'presentación',
            'producto.variantes.*.unidad_id' => 'unidad',
            'producto.variantes.*.sedes.*.precio' => 'precio de la sede',
            'producto.variantes.*.sedes.*.stock_minimo' => 'stock mínimo',
            'producto.variantes.*.color_id' => 'color',
            'producto.variantes.*.sku' => 'SKU',
            'producto.variantes.*.codigo_barras' => 'código de barras',
            'producto.variantes.*.precio_mayor' => 'precio por mayor',
            'producto.variantes.*.precio' => 'precio',
            'producto.variantes.*.stock_inicial' => 'stock inicial',
            'producto.variantes.*.costo_unitario' => 'costo',
            'producto.variantes.*.lote' => 'lote',
            'producto.variantes.*.vence_at' => 'vencimiento',
            'producto.costo_compra' => 'costo de compra',
            'producto.referencia_compra' => 'factura o guía',
            'producto.archivos.*.archivo' => 'foto',
            'producto.variantes.*.archivos.*.archivo' => 'foto',
        ];
    }
}
