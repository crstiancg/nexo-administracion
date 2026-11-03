<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tipo_documento', 'numero_documento', 'nombre', 'mayorista', 'telefono', 'email', 'direccion'])]
class Cliente extends Model
{
    public const DNI = 'DNI';

    public const RUC = 'RUC';

    /** Carné de extranjería: no se consulta en ninguna API. */
    public const CE = 'CE';

    public const TIPOS_DOCUMENTO = [self::DNI, self::RUC, self::CE];

    /** Dígitos exactos de cada documento. */
    public const LONGITUDES = [self::DNI => 8, self::RUC => 11];

    protected function casts(): array
    {
        return [
            // Empresa a la que se le vende a precio por mayor.
            'mayorista' => 'boolean',
        ];
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }
}
