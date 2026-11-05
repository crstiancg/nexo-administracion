<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un ajuste del sistema (clave → valor JSON). Se lee y escribe a través de
 * App\Support\Empresa.
 */
#[Fillable(['clave', 'valor'])]
class Configuracion extends Model
{
    use Auditable;

    /**
     * Explícita: por convención Laravel pluraliza en inglés.
     */
    protected $table = 'configuraciones';

    protected function casts(): array
    {
        return ['valor' => 'array'];
    }
}
