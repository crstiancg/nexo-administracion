<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un premio del sorteo, en orden (1.°, 2.°…). El ganador (`ticket_id`) lo
 * pone App\Services\Sorteos al sortear; si no se presenta, se descarta y
 * queda en `descartes` para el acta.
 */
class SorteoPremio extends Model
{
    protected $fillable = ['orden', 'descripcion'];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'participantes' => 'integer',
            'descartes' => 'array',
            'sorteado_at' => 'datetime',
        ];
    }

    public function sorteo(): BelongsTo
    {
        return $this->belongsTo(Sorteo::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SorteoTicket::class);
    }

    public function sorteadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorteado_por');
    }
}
