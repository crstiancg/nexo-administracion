<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una PC que se cotiza seguido ("PC Oficina Core i5"). `items`: los
 * casilleros [{componente, variante_id, descripcion, unidad, cantidad,
 * precio_unitario}]. Al cargarla, los del catálogo toman el precio de HOY;
 * el guardado sólo vale para los libres.
 */
#[Fillable(['nombre', 'garantia', 'items'])]
class PlantillaPc extends Model
{
    /**
     * Explícita: por convención Laravel pluraliza en inglés ("plantilla_pcs").
     */
    protected $table = 'plantillas_pc';

    protected function casts(): array
    {
        return [
            'items' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
