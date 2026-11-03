<?php

namespace App\Http\Resources;

use App\Models\Oferta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Oferta
 */
class OfertaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'alcance' => $this->categorias->isNotEmpty() ? 'categorias' : 'productos',
            'productos' => $this->productosConVariantes(),
            'categorias' => $this->categorias->map->only(['id', 'nombre'])->values(),
            'incluye_subcategorias' => $this->incluye_subcategorias,
            'tipo' => $this->tipo,
            'valor' => $this->valor,
            'etiqueta' => $this->etiqueta(),
            // Con zona horaria: el navegador la muestra en hora local.
            'inicia_at' => $this->inicia_at->toIso8601String(),
            'termina_at' => $this->termina_at->toIso8601String(),
            'activa' => $this->activa,
            // pausada | programada | vigente | vencida
            'estado' => $this->estado(),
        ];
    }

    /**
     * Cada producto de la oferta con las variantes elegidas: vacío = el
     * producto completo. Las variantes puntuales se agrupan bajo su producto.
     *
     * @return array<int, array<string, mixed>>
     */
    private function productosConVariantes(): array
    {
        $productos = $this->productos->mapWithKeys(fn ($p) => [$p->id => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'precio' => $p->precio,
            'variantes' => [],
        ]])->all();

        foreach ($this->variantes as $v) {
            $productos[$v->producto_id] ??= [
                'id' => $v->producto->id,
                'nombre' => $v->producto->nombre,
                'precio' => $v->producto->precio,
                'variantes' => [],
            ];
            $productos[$v->producto_id]['variantes'][] = [
                'id' => $v->id,
                'sku' => $v->sku,
                'presentacion' => $v->presentacion,
                'color' => $v->color?->only(['nombre', 'hexadecimal']),
            ];
        }

        return array_values($productos);
    }
}
