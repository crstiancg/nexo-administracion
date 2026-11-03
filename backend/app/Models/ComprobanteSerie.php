<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * La serie de un tipo de comprobante en una sede (B001, F001…) y el último
 * correlativo emitido. Sin fillable: el correlativo sólo lo mueve
 * App\Services\Facturacion, con la fila bloqueada.
 */
class ComprobanteSerie extends Model
{
    protected $table = 'comprobante_series';

    protected function casts(): array
    {
        return ['correlativo' => 'integer'];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Las series de una sede nueva: la próxima numeración libre para boleta y
     * factura (B004/F004 si ya hay tres sedes).
     */
    public static function crearPara(Sede $sede): void
    {
        foreach ([Comprobante::BOLETA => 'B', Comprobante::FACTURA => 'F'] as $tipo => $letra) {
            if (self::query()->where('sede_id', $sede->id)->where('tipo', $tipo)->exists()) {
                continue;
            }

            $numero = (int) self::query()->where('tipo', $tipo)->max(DB::raw('CAST(SUBSTRING(serie, 2) AS UNSIGNED)')) + 1;
            (new self)->forceFill([
                'sede_id' => $sede->id,
                'tipo' => $tipo,
                'serie' => $letra.str_pad((string) $numero, 3, '0', STR_PAD_LEFT),
                'correlativo' => 0,
            ])->save();
        }
    }
}
