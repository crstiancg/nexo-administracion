<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El adelanto de una orden de servicio: el pago que dejó el cliente al
 * recibir el equipo (o al editar la orden). Se guarda cuál es para poder
 * corregirlo el mismo día (devolución + cobro nuevo) sin confundirlo con los
 * abonos que se registran desde el detalle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->foreignId('anticipo_pago_id')->nullable()->after('pedido_id')->constrained('pagos')->nullOnDelete();
        });

        // Las órdenes que ya tienen adelanto: el primer cobro de su pedido,
        // registrado junto con la orden (en la misma transacción).
        DB::table('ordenes_servicio')->whereNotNull('pedido_id')->orderBy('id')
            ->each(function ($orden) {
                $desde = $orden->recibido_at ?? $orden->created_at;
                if (! $desde) {
                    return;
                }

                $pagoId = DB::table('pagos')
                    ->where('pedido_id', $orden->pedido_id)
                    ->where('monto', '>', 0)
                    ->where('created_at', '<=', date('Y-m-d H:i:s', strtotime($desde) + 60))
                    ->orderBy('id')
                    ->value('id');

                if ($pagoId) {
                    DB::table('ordenes_servicio')->where('id', $orden->id)->update(['anticipo_pago_id' => $pagoId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anticipo_pago_id');
        });
    }
};
