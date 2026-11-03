<?php

namespace App\Models;

use App\Support\Fechas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un lote de una presentación en una sede. Sin fillable a propósito: lo crea
 * y lo mueve sólo App\Services\Inventario.
 */
class Lote extends Model
{
    /** "Por vencer" = vence dentro de estos días. */
    public const DIAS_POR_VENCER = 30;

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'vence_at' => 'date',
        ];
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function vencido(): bool
    {
        return $this->vence_at !== null && $this->vence_at->lt(Fechas::hoy());
    }

    /**
     * vencido | por_vencer | vigente | sin_fecha
     */
    public function estado(): string
    {
        return match (true) {
            $this->vence_at === null => 'sin_fecha',
            $this->vencido() => 'vencido',
            $this->vence_at->lte(Fechas::hoy()->addDays(self::DIAS_POR_VENCER)) => 'por_vencer',
            default => 'vigente',
        };
    }
}
