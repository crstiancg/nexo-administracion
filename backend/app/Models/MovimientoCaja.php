<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ingreso o egreso de efectivo que no es una venta. INMUTABLE, como los pagos.
 */
#[Fillable(['caja_id', 'tipo', 'monto', 'concepto', 'user_id'])]
class MovimientoCaja extends Model
{
    public const INGRESO = 'ingreso';

    public const EGRESO = 'egreso';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de caja no se editan.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de caja no se borran.'));
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
