<?php

namespace App\Http\Resources;

use App\Models\Variante;
use App\Services\Precios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Una variante vista desde inventario: qué es (producto, presentación, color, SKU) y
 * cuánto hay. Sirve para el buscador de las líneas y para el historial.
 *
 * @mixin Variante
 */
class VarianteStockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    private function conPrecio(): bool
    {
        return $this->relationLoaded('producto') && array_key_exists('precio', $this->producto->getAttributes());
    }

    /**
     * @return array{precio: string, precio_lista: string, oferta: ?string}
     */
    private function precioVigente(): array
    {
        $vigente = app(Precios::class)->vigente(
            $this->precioBase((float) $this->producto->precio),
            $this->producto->id,
            $this->producto->categoria_id,
            $this->id,
        );

        return [
            'precio' => $vigente['precio'],
            'precio_lista' => $vigente['precio_lista'],
            'oferta' => $vigente['oferta']?->etiqueta(),
            // Para clientes mayoristas (null = no tiene).
            'precio_mayor' => $this->precio_mayor,
        ];
    }

    private function miniatura(): ?string
    {
        $archivo = $this->portada
            ?? ($this->relationLoaded('producto') && $this->producto->relationLoaded('portada') ? $this->producto->portada : null);

        return $archivo ? Storage::disk('public')->url($archivo->miniatura ?? $archivo->ruta) : null;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'codigo_barras' => $this->codigo_barras,
            // El de la sede consultada (o el total, si no se pidió por sede).
            'stock' => $this->stockVisible(),
            'stock_total' => (float) $this->stock,
            // El de la sede consultada.
            'stock_minimo' => $this->stockMinimoVisible(),
            'costo_promedio' => $this->costo_promedio,
            // Precio de venta HOY (con la mejor oferta vigente) y el de lista.
            // Sólo cuando se cargó el precio del producto.
            ...($this->conPrecio() ? $this->precioVigente() : []),
            'producto' => $this->whenLoaded('producto', fn () => $this->producto->only(['id', 'nombre'])),
            'maneja_lotes' => $this->whenLoaded('producto', fn () => (bool) $this->producto->maneja_lotes),
            'marca' => $this->when(
                $this->relationLoaded('producto') && $this->producto->relationLoaded('marca'),
                fn () => $this->producto->marca?->only(['id', 'nombre']),
            ),
            'presentacion' => $this->presentacion,
            'unidad' => $this->whenLoaded('unidad', fn () => $this->unidad->only(['nombre', 'abreviatura', 'fraccionable'])),
            'color' => $this->whenLoaded('color', fn () => $this->color?->only(['nombre', 'hexadecimal'])),
            // Foto de la presentación o, si no tiene, la del producto (punto de venta).
            'miniatura_url' => $this->when($this->relationLoaded('portada'), fn () => $this->miniatura()),
        ];
    }
}
