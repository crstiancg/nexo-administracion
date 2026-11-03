<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una cuota del crédito: cuánto y cuándo. Lo pagado de cada una se calcula
 * (Credito::estadoCuotas), no se guarda.
 */
#[Fillable(['numero', 'vence', 'monto'])]
class CreditoCuota extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'vence' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class);
    }
}
