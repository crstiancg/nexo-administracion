<?php

namespace App\Http\Resources;

use App\Models\Producto;
use App\Services\Precios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Un producto tal como lo muestra el punto de venta: su tarjeta (portada,
 * precio, stock, vendidos) y sus presentaciones para elegir cuál vender.
 *
 * @mixin Producto
 */
class CatalogoProductoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disco = Storage::disk('public');
        $miniatura = fn ($archivo) => $archivo ? $disco->url($archivo->miniatura ?? $archivo->ruta) : null;

        $precios = app(Precios::class);

        // Precio de hoy de cada variante (su oferta puede ser del producto,
        // de la variante puntual o de la categoría).
        $variantes = $this->variantes->map(fn ($v) => [
            'variante' => $v,
            ...$precios->vigente($v->precioBase((float) $this->precio), $this->id, $this->categoria_id, $v->id),
        ]);

        // Badge de la tarjeta: la oferta más fuerte entre sus variantes ("-30%
        // sólo en rojo" también se ve en el catálogo).
        $delProducto = $variantes
            ->filter(fn ($x) => $x['oferta'])
            ->sortBy(fn ($x) => (float) $x['precio'] / max((float) $x['precio_lista'], 0.01))
            ->first()['oferta'] ?? null;

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'precio' => $this->precio,
            'categoria' => $this->categoria?->only(['id', 'nombre']),
            'marca' => $this->marca?->only(['id', 'nombre']),
            'miniatura_url' => $miniatura($this->portada),
            'oferta' => $delProducto ? [
                'id' => $delProducto->id,
                'nombre' => $delProducto->nombre,
                'etiqueta' => $delProducto->etiqueta(),
                'termina_at' => $delProducto->termina_at->toIso8601String(),
            ] : null,
            // De la sede del usuario.
            // Sin lo que está en lotes vencidos (no se vende).
            'stock_total' => max(0.0, round((float) $this->stock_total - (float) ($this->stock_vencido ?? 0), 3)),
            // Unidades vendidas en los últimos 90 días (confirmadas o entregadas).
            'vendidos' => (float) $this->vendidos,
            'variantes' => $variantes->map(fn ($x) => [
                'id' => $x['variante']->id,
                'sku' => $x['variante']->sku,
                'codigo_barras' => $x['variante']->codigo_barras,
                'stock' => $x['variante']->stockVendible(),
                // Precio de venta HOY (con oferta, si hay) y el de lista (el
                // de la variante o el base del producto), para tacharlo.
                'precio' => $x['precio'],
                'precio_lista' => $x['precio_lista'],
                'precio_mayor' => $x['variante']->precio_mayor,
                'oferta' => $x['oferta']?->etiqueta(),
                'presentacion' => $x['variante']->presentacion,
                'unidad' => $x['variante']->unidad?->only(['id', 'nombre', 'abreviatura', 'fraccionable']),
                'color' => $x['variante']->color?->only(['id', 'nombre', 'hexadecimal']),
                // La foto de la presentación; si no tiene, la del producto.
                'miniatura_url' => $miniatura($x['variante']->portada) ?? $miniatura($this->portada),
            ])->values(),
        ];
    }
}
