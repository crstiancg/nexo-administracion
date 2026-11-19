<?php

namespace App\Models;

use App\Support\Fechas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * abierto → sorteando (salió el primer premio: ya no entran tickets) →
 * sorteado (salieron todos) | cancelado. Abierto se muestra como
 * "programado" antes de empezar y "por sortear" después de terminar. Los
 * tickets y los premios cambian sólo por App\Services\Sorteos.
 */
#[Fillable(['nombre', 'bases', 'periodo', 'inicia_el', 'termina_el', 'precio_ticket', 'un_premio_por_cliente'])]
class Sorteo extends Model
{
    public const ABIERTO = 'abierto';

    public const SORTEANDO = 'sorteando';

    public const SORTEADO = 'sorteado';

    public const CANCELADO = 'cancelado';

    public const PERIODOS = ['mensual', 'trimestral', 'otro'];

    protected function casts(): array
    {
        return [
            'inicia_el' => 'date',
            'termina_el' => 'date',
            'precio_ticket' => 'decimal:2',
            'un_premio_por_cliente' => 'boolean',
            'sorteado_at' => 'datetime',
        ];
    }

    /** programado | abierto | por_sortear | sorteando | sorteado | cancelado */
    public function estadoVisible(): string
    {
        if ($this->estado !== self::ABIERTO) {
            return $this->estado;
        }
        $hoy = Fechas::hoy();
        if ($this->inicia_el->gt($hoy)) {
            return 'programado';
        }

        return $this->termina_el->lt($hoy) ? 'por_sortear' : self::ABIERTO;
    }

    /** Entran tickets: abierto y hoy dentro del período. */
    public function recibeTickets(): bool
    {
        return $this->estadoVisible() === self::ABIERTO;
    }

    /** Ya se puede sortear: el último día del período o después. */
    public function sePuedeSortear(): bool
    {
        return in_array($this->estado, [self::ABIERTO, self::SORTEANDO], true)
            && $this->termina_el->lte(Fechas::hoy());
    }

    /** ¿Participa esta sede? Sin sedes elegidas, participan todas. */
    public function participaSede(?int $sedeId): bool
    {
        $sedes = $this->relationLoaded('sedes') ? $this->sedes : $this->sedes()->get();

        return $sedes->isEmpty() || $sedes->contains('id', $sedeId);
    }

    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SorteoTicket::class);
    }

    public function premios(): HasMany
    {
        return $this->hasMany(SorteoPremio::class)->orderBy('orden');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
