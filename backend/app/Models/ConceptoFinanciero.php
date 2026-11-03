<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cómo se clasifica un gasto (alquiler, sueldos, luz…) o un ingreso que no
 * es una venta (préstamo, venta de chatarra…).
 */
#[Fillable(['tipo', 'nombre', 'activo'])]
class ConceptoFinanciero extends Model
{
    public const INGRESO = 'ingreso';

    public const GASTO = 'gasto';

    public const TIPOS = [self::INGRESO => 'Ingreso', self::GASTO => 'Gasto'];

    /**
     * Explícita: por convención Laravel pluraliza en inglés.
     */
    protected $table = 'conceptos_financieros';

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoFinanciero::class, 'concepto_id');
    }
}
