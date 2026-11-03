<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'abreviatura', 'codigo_sunat', 'fraccionable'])]
class Unidad extends Model
{
    /**
     * Explícita: por convención Laravel pluraliza en inglés ("unidads").
     */
    protected $table = 'unidades';

    protected function casts(): array
    {
        return [
            'fraccionable' => 'boolean',
        ];
    }

    public function variantes(): HasMany
    {
        return $this->hasMany(Variante::class);
    }
}
