<?php

namespace App\Models;

use App\Support\Fechas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Una presentación de un producto ("Cartucho 300 ml", opcionalmente en un
 * color): lo que efectivamente tiene SKU y stock. Es común a todas las
 * sedes; cada sede la habilita y le pone precio y mínimo en `stocks`.
 * `stock` y `costo_promedio` no son fillable a propósito: sólo los mueve
 * App\Services\Inventario, dejando su movimiento en el libro.
 * `codigo_barras` es el EAN-13 que registra el usuario (el de fábrica):
 * obligatorio y único (StoreProductoRequest).
 */
#[Fillable(['presentacion', 'unidad_id', 'color_id', 'sku', 'codigo_barras', 'precio', 'precio_mayor'])]
class Variante extends Model
{
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            // Venta por mayor a empresas: igual en todas las sedes; null = no tiene.
            'precio_mayor' => 'decimal:2',
            // float y no decimal:3: el front compara números (stock > 0).
            'stock' => 'float',
            'costo_promedio' => 'decimal:4',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }

    /**
     * Agrega lo de esa sede: `stock_sede` (0 si nunca tuvo), `precio_sede`
     * (null = el general) y `stock_minimo_sede`.
     */
    public function scopeConStockDeSede(Builder $query, int $sedeId): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('variantes.*');
        }

        $deLaSede = fn (string $columna) => Stock::query()
            ->select($columna)
            ->whereColumn('stocks.variante_id', 'variantes.id')
            ->where('stocks.sede_id', $sedeId)
            ->limit(1);

        $query->addSelect([
            'stock_sede' => Stock::query()
                ->selectRaw('COALESCE(SUM(cantidad), 0)')
                ->whereColumn('stocks.variante_id', 'variantes.id')
                ->where('stocks.sede_id', $sedeId),
            'precio_sede' => $deLaSede('precio'),
            'stock_minimo_sede' => $deLaSede('stock_minimo'),
        ]);
    }

    /**
     * Agrega `stock_vencido`: lo que hay en lotes vencidos de esa sede. Está
     * en el stock pero no se puede vender (las ventas nunca sacan vencidos).
     */
    public function scopeConStockVencidoDeSede(Builder $query, int $sedeId): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('variantes.*');
        }

        $query->addSelect(['stock_vencido' => Lote::query()
            ->selectRaw('COALESCE(SUM(cantidad), 0)')
            ->whereColumn('lotes.variante_id', 'variantes.id')
            ->where('lotes.sede_id', $sedeId)
            ->whereDate('lotes.vence_at', '<', Fechas::hoy())]);
    }

    /**
     * Lo que se puede vender: el stock de la sede menos lo vencido (si se
     * pidió con conStockVencidoDeSede()).
     */
    public function stockVendible(): float
    {
        return max(0.0, round($this->stockVisible() - (float) ($this->attributes['stock_vencido'] ?? 0), 3));
    }

    /**
     * Sólo las que esa sede vende.
     */
    public function scopeHabilitadaEnSede(Builder $query, int $sedeId): void
    {
        $query->whereHas('stocks', fn (Builder $s) => $s->where('sede_id', $sedeId)->where('activo', true));
    }

    /**
     * Precio de lista: el de la sede (si se pidió con conStockDeSede() y
     * tiene uno propio), si no el de la presentación, si no el del producto.
     */
    public function precioBase(?float $precioProducto = null): float
    {
        $deLaSede = $this->attributes['precio_sede'] ?? null;

        return (float) ($deLaSede ?? $this->precio ?? $precioProducto ?? $this->producto->precio);
    }

    /**
     * El mínimo de la sede consultada (0 si no se pidió por sede).
     */
    public function stockMinimoVisible(): float
    {
        return (float) ($this->attributes['stock_minimo_sede'] ?? 0);
    }

    /**
     * El stock que corresponde mostrar: el de la sede si se pidió con
     * conStockDeSede(), si no el total de la empresa.
     */
    public function stockVisible(): float
    {
        return array_key_exists('stock_sede', $this->attributes)
            ? (float) $this->attributes['stock_sede']
            : (float) $this->stock;
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->orderBy('orden');
    }

    /**
     * La primera foto de la presentación (orden 0), para el punto de venta.
     */
    public function portada(): MorphOne
    {
        return $this->morphOne(Archivo::class, 'archivable')->ofMany('orden', 'min');
    }
}
