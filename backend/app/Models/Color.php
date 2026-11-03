<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'hexadecimal'])]
class Color extends Model
{
    /**
     * Explícita: por convención Laravel pluraliza en inglés ("colors").
     */
    protected $table = 'colores';

    public function variantes(): HasMany
    {
        return $this->hasMany(Variante::class);
    }
}
