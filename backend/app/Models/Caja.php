<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una sesión de caja: se abre con un monto inicial, recibe pagos y
 * movimientos, y se cierra con el arqueo de efectivo. Cerrada no se reabre.
 * Se abre y cierra sólo a través de App\Services\Cajas (sin fillable a
 * propósito).
 */
class Caja extends Model
{
    public const ABIERTA = 'abierta';

    public const CERRADA = 'cerrada';

    protected function casts(): array
    {
        return [
            'abierta' => 'boolean',
            'monto_apertura' => 'decimal:2',
            'monto_esperado' => 'decimal:2',
            'monto_contado' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'abierta_at' => 'datetime',
            'cerrada_at' => 'datetime',
        ];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class);
    }

    public function abiertaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abierta_por');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public function estaAbierta(): bool
    {
        return $this->estado === self::ABIERTA;
    }

    /**
     * Abierta en un día anterior (en la zona del negocio): la caja es diaria,
     * así que hay que cerrarla antes de seguir registrando dinero.
     */
    public function esDeOtroDia(): bool
    {
        $zona = config('app.zona_negocio');

        return $this->estaAbierta()
            && $this->abierta_at->copy()->setTimezone($zona)->toDateString() < now($zona)->toDateString();
    }
}
