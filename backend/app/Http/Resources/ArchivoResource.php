<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Contrato de salida de un archivo. Nunca expone la ruta en disco ni a qué
 * modelo pertenece: sólo lo que el front necesita para mostrarlo.
 *
 * @mixin \App\Models\Archivo
 */
class ArchivoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disco = Storage::disk('public');

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'mime' => $this->mime,
            'tamano' => $this->tamano,
            'ancho' => $this->ancho,
            'alto' => $this->alto,
            // Original: para ampliar o descargar.
            'url' => $disco->url($this->ruta),
            // Liviana: para listados y galerías. Sin miniatura, el original.
            'miniatura_url' => $disco->url($this->miniatura ?? $this->ruta),
        ];
    }
}
