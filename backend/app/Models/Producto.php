<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['nombre', 'categoria_id', 'marca_id', 'descripcion', 'precio', 'activo', 'maneja_lotes'])]
class Producto extends Model
{
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
            'maneja_lotes' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function variantes(): HasMany
    {
        return $this->hasMany(Variante::class);
    }

    /**
     * El stock de todas sus presentaciones, por sede.
     */
    public function stocks(): HasManyThrough
    {
        return $this->hasManyThrough(Stock::class, Variante::class);
    }

    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->orderBy('orden');
    }

    /**
     * La primera foto (orden 0), para la miniatura del listado sin traer
     * toda la galería de cada producto.
     */
    public function portada(): MorphOne
    {
        return $this->morphOne(Archivo::class, 'archivable')->ofMany('orden', 'min');
    }
}
