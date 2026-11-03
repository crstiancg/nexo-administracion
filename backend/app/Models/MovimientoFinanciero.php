<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un gasto o un ingreso que no es venta. Si fue en efectivo del cajón, tiene
 * su movimiento de caja (que no se borra: el registro queda fijo).
 */
#[Fillable(['tipo', 'concepto_id', 'monto', 'metodo', 'fecha', 'descripcion', 'referencia'])]
class MovimientoFinanciero extends Model
{
    /**
     * Explícita: por convención Laravel pluraliza en inglés.
     */
    protected $table = 'movimientos_financieros';

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoFinanciero::class, 'concepto_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimientoCaja(): BelongsTo
    {
        return $this->belongsTo(MovimientoCaja::class);
    }

    public function salioDeCaja(): bool
    {
        return $this->movimiento_caja_id !== null;
    }
}
