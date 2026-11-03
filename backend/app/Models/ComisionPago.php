<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La comisión de un empleado pagada por un período (también es un gasto).
 */
#[Fillable(['user_id', 'desde', 'hasta', 'monto'])]
class ComisionPago extends Model
{
    protected function casts(): array
    {
        return [
            'desde' => 'date',
            'hasta' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoFinanciero::class, 'movimiento_financiero_id');
    }
}
