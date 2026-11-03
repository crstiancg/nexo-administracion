<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Laptop, Celular, Impresora… Cada tipo trae la lista de revisión que se
 * marca al recibir el equipo (`checklist`: ["Enciende", "Pantalla", …]).
 */
#[Fillable(['nombre', 'checklist', 'activo'])]
class TipoEquipo extends Model
{
    /**
     * Explícita: por convención Laravel pluraliza en inglés ("tipo_equipos").
     */
    protected $table = 'tipos_equipo';

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenServicio::class);
    }
}
