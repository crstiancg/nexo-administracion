<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

/**
 * Precio promocional por un período, para una lista de destinos (tabla
 * polimórfica `ofertables`): productos completos, variantes puntuales y/o
 * categorías. No toca los precios guardados: el precio de venta se calcula
 * en App\Services\Precios, y lo que se cobró queda congelado en cada ítem del
 * pedido (terminada la oferta, el historial no cambia).
 */
#[Fillable(['nombre', 'incluye_subcategorias', 'tipo', 'valor', 'inicia_at', 'termina_at', 'activa'])]
class Oferta extends Model
{
    public const PORCENTAJE = 'porcentaje';

    public const PRECIO_FIJO = 'precio_fijo';

    protected function casts(): array
    {
        return [
            'incluye_subcategorias' => 'boolean',
            'valor' => 'decimal:2',
            'inicia_at' => 'datetime',
            'termina_at' => 'datetime',
            'activa' => 'boolean',
        ];
    }

    /** Productos completos: todas sus variantes, también las que se agreguen después. */
    public function productos(): MorphToMany
    {
        return $this->morphedByMany(Producto::class, 'ofertable');
    }

    /** Variantes puntuales (el Sikaflex gris en cartucho), sin el resto del producto. */
    public function variantes(): MorphToMany
    {
        return $this->morphedByMany(Variante::class, 'ofertable');
    }

    public function categorias(): MorphToMany
    {
        return $this->morphedByMany(Categoria::class, 'ofertable');
    }

    /**
     * Activas y dentro de su período en este momento.
     */
    public function scopeVigentes(Builder $query, ?Carbon $momento = null): void
    {
        $momento ??= now();

        $query->where('activa', true)
            ->where('inicia_at', '<=', $momento)
            ->where('termina_at', '>', $momento);
    }

    /**
     * pausada | programada | vigente | vencida
     */
    public function estado(?Carbon $momento = null): string
    {
        $momento ??= now();

        return match (true) {
            $this->termina_at <= $momento => 'vencida',
            ! $this->activa => 'pausada',
            $this->inicia_at > $momento => 'programada',
            default => 'vigente',
        };
    }

    /**
     * El precio de venta que deja esta oferta sobre un precio de lista.
     */
    public function aplicarA(float $precioLista): float
    {
        return $this->tipo === self::PORCENTAJE
            ? round($precioLista * (1 - (float) $this->valor / 100), 2)
            : round((float) $this->valor, 2);
    }

    /**
     * "-20%" o "a S/ 29.90", para el badge del catálogo.
     */
    public function etiqueta(): string
    {
        return $this->tipo === self::PORCENTAJE
            ? '-'.rtrim(rtrim(number_format((float) $this->valor, 2, '.', ''), '0'), '.').'%'
            : 'a S/ '.number_format((float) $this->valor, 2);
    }
}
