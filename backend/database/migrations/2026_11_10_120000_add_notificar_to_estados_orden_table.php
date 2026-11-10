<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Si al pasar a este estado se ofrece avisar al cliente por WhatsApp (la
 * casilla del diálogo "Cambiar estado" viene marcada). Se puede desmarcar
 * en cada orden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estados_orden', function (Blueprint $table) {
            $table->boolean('notificar')->default(false)->after('mensaje_cliente');
        });

        // Lo que al cliente le importa saber: que algo lo frena, que ya puede
        // recoger, que se entregó o que sigue sin recogerlo.
        DB::table('estados_orden')
            ->whereIn('clave', ['esperando_repuesto', 'reparado', 'sin_reparacion', 'entregado', 'abandonado'])
            ->update(['notificar' => true]);
    }

    public function down(): void
    {
        Schema::table('estados_orden', function (Blueprint $table) {
            $table->dropColumn('notificar');
        });
    }
};
