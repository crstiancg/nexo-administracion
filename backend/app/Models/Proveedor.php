<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ruc', 'razon_social', 'contacto', 'telefono', 'email', 'direccion', 'activo'])]
class Proveedor extends Model
{
    use Auditable;

    /**
     * Explícita: por convención Laravel pluraliza en inglés ("proveedors").
     */
    protected $table = 'proveedores';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }
}
