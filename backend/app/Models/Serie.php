<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Una unidad con número de serie de un producto que `maneja_series`. Sólo
 * la mueve App\Services\Series, junto con el movimiento de inventario.
 */
class Serie extends Model
{
    public const EN_STOCK = 'en_stock';

    public const VENDIDA = 'vendida';

    /** Salió por otra cosa: merma, daño, devolución al proveedor… */
    public const BAJA = 'baja';

    protected $table = 'series';

    protected $guarded = [];

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function movimientos(): BelongsToMany
    {
        return $this->belongsToMany(MovimientoInventario::class, 'movimiento_serie')->orderBy('movimiento_inventarios.id');
    }

    /** "ab-123 " → "AB-123": así se escribe igual al escanear o a mano. */
    public static function normalizar(string $numero): string
    {
        return mb_strtoupper(trim($numero));
    }
}
