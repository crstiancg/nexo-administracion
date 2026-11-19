<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vale de fidelización: un % de descuento sobre la mano de obra de la
 * próxima orden de servicio del cliente. Se genera en una orden
 * (`orden_origen_id`) y se usa en otra (`orden_uso_id`). No vence.
 * Se crea y se aplica sólo por App\Services\OrdenesServicio.
 */
class Vale extends Model
{
    protected function casts(): array
    {
        return [
            'porcentaje' => 'integer',
            'usado_at' => 'datetime',
        ];
    }

    /** "VALE-7K2PQX": sin letras que se confunden (0/O, 1/I). */
    public static function nuevoCodigo(): string
    {
        do {
            $codigo = 'VALE-'.substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 3)), 0, 6);
        } while (static::query()->where('codigo', $codigo)->exists());

        return $codigo;
    }

    public function scopeDisponibles(Builder $query): void
    {
        $query->whereNull('orden_uso_id');
    }

    public function disponible(): bool
    {
        return $this->orden_uso_id === null;
    }

    /** Lo que descuenta sobre una mano de obra de `$manoObra` soles. */
    public function descuentoSobre(float $manoObra): float
    {
        return round($manoObra * $this->porcentaje / 100, 2);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function ordenOrigen(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_origen_id');
    }

    public function ordenUso(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_uso_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Para la lista de la orden: lo que se muestra y se manda por WhatsApp. */
    public function resumen(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'porcentaje' => $this->porcentaje,
            'disponible' => $this->disponible(),
            'origen' => $this->relationLoaded('ordenOrigen') ? $this->ordenOrigen?->codigo : null,
            'usado_en' => $this->relationLoaded('ordenUso') ? $this->ordenUso?->codigo : null,
            'usado_at' => $this->usado_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
