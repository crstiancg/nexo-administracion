<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'direccion', 'telefono', 'codigo_sunat', 'activo'])]
class Sede extends Model
{
    /**
     * Cada sede nueva nace con su serie de boleta y de factura.
     */
    protected static function booted(): void
    {
        static::created(fn (Sede $sede) => ComprobanteSerie::crearPara($sede));
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function seriesComprobante(): HasMany
    {
        return $this->hasMany(ComprobanteSerie::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
