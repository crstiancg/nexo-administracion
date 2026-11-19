<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una chance en un sorteo. Nace de una venta entregada (App\Services\Sorteos)
 * y se anula si la venta se anula. No es fillable: sólo lo crea el servicio.
 */
class SorteoTicket extends Model
{
    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'anulado_at' => 'datetime',
        ];
    }

    /** "#000123" */
    public function codigo(): string
    {
        return '#'.str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('sorteo_tickets.anulado_at');
    }

    public function sorteo(): BelongsTo
    {
        return $this->belongsTo(Sorteo::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
