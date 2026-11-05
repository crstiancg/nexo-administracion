<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un cambio registrado por App\Models\Concerns\Auditable. Sólo se crea: no
 * se edita ni se borra desde el sistema.
 */
class Auditoria extends Model
{
    public const UPDATED_AT = null;

    /** Lo que se muestra en la pantalla para cada modelo auditado. */
    public const MODELOS = [
        'Producto' => 'Producto',
        'Variante' => 'Presentación',
        'Pedido' => 'Pedido',
        'Pago' => 'Pago',
        'Caja' => 'Caja',
        'Credito' => 'Crédito',
        'Compra' => 'Compra',
        'Cliente' => 'Cliente',
        'Proveedor' => 'Proveedor',
        'Oferta' => 'Oferta',
        'MovimientoFinanciero' => 'Ingreso o gasto',
        'User' => 'Usuario',
        'Sede' => 'Sede',
        'Configuracion' => 'Configuración',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
