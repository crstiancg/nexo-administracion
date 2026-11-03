<?php

namespace App\Http\Resources;

use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Variante
 */
class VarianteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'presentacion' => $this->presentacion,
            'unidad_id' => $this->unidad_id,
            'color_id' => $this->color_id,
            'unidad' => $this->whenLoaded('unidad', fn () => $this->unidad->only(['id', 'nombre', 'abreviatura', 'fraccionable'])),
            'color' => $this->whenLoaded('color', fn () => $this->color?->only(['id', 'nombre', 'hexadecimal'])),
            'sku' => $this->sku,
            // Lo asigna el sistema al crear: el form sólo lo muestra.
            'codigo_barras' => $this->codigo_barras,
            // null = usa el precio base del producto.
            'precio' => $this->precio,
            // Venta por mayor a empresas (null = no tiene).
            'precio_mayor' => $this->precio_mayor,
            // Costo promedio (para ver la ganancia): sólo a quien administra
            // productos o ve reportes, no a cualquier vendedor.
            'costo_promedio' => $this->when(
                Permisos::puede($request->user(), 'productos.update') || Permisos::puede($request->user(), 'reportes.ventas'),
                fn () => $this->costo_promedio,
            ),
            // Total de la empresa y, en el detalle, cada sede: su stock y cómo
            // la vende (activo, precio propio, mínimo).
            'stock' => $this->stock,
            'stocks' => $this->whenLoaded('stocks', fn () => $this->stocks->map(fn ($s) => [
                'sede_id' => $s->sede_id,
                'sede' => $s->sede?->nombre,
                'cantidad' => $s->cantidad,
                'activo' => $s->activo,
                'precio' => $s->precio,
                'stock_minimo' => $s->stock_minimo,
            ])->values()),
            // Con historial de inventario no se puede quitar del producto.
            'con_movimientos' => $this->whenHas('movimientos_exists', fn ($existe) => (bool) $existe),
            'archivos' => ArchivoResource::collection($this->whenLoaded('archivos')),
        ];
    }
}
