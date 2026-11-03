<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Un cobro (o, con monto negativo, una devolución) de un pedido, dentro de
 * una caja. INMUTABLE: se registra a través de App\Services\Cajas y un error
 * se corrige con una devolución.
 */
#[Fillable(['pedido_id', 'caja_id', 'metodo', 'monto', 'recibido', 'vuelto', 'referencia', 'motivo', 'user_id'])]
class Pago extends Model
{
    public const METODOS = [
        'efectivo' => 'Efectivo',
        'yape' => 'Yape',
        'plin' => 'Plin',
        'transferencia' => 'Transferencia',
        'tarjeta' => 'Tarjeta',
    ];

    public const EFECTIVO = 'efectivo';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'recibido' => 'decimal:2',
            'vuelto' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los pagos no se editan: registrá una devolución.'));
        static::deleting(fn () => throw new LogicException('Los pagos no se borran: registrá una devolución.'));
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function esDevolucion(): bool
    {
        return (float) $this->monto < 0;
    }
}
