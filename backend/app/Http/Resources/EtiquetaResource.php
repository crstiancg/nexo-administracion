<?php

namespace App\Http\Resources;

use App\Models\Variante;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una variante vista para imprimir su etiqueta: sólo lo que va impreso y lo
 * que ayuda a elegirla. Sin costos ni precios: imprimir etiquetas no tiene
 * por qué dar acceso a eso.
 *
 * @mixin Variante
 */
class EtiquetaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_barras' => $this->codigo_barras,
            'sku' => $this->sku,
            // Sólo informativo: ayuda a decidir cuántas imprimir.
            'stock' => $this->stockVisible(),
            'producto' => $this->producto->only(['id', 'nombre']),
            'presentacion' => $this->presentacion,
            'unidad' => $this->unidad->abreviatura,
            'color' => $this->color?->only(['nombre', 'hexadecimal']),
        ];
    }
}
