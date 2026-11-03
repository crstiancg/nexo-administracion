<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Adjunto polimórfico. El archivo físico vive en el disco `public`; se crea y
 * se borra sólo a través de App\Services\ArchivosService, y sale a la API
 * sólo por App\Http\Resources\ArchivoResource.
 */
#[Fillable(['nombre', 'ruta', 'miniatura', 'mime', 'tamano', 'ancho', 'alto', 'orden'])]
class Archivo extends Model
{
    public function archivable(): MorphTo
    {
        return $this->morphTo();
    }
}
