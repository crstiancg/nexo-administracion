<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una cotización también se convierte en orden de servicio (un presupuesto
 * de reparación que el cliente aceptó): queda "convertida" y apunta a la OS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->foreignId('orden_servicio_id')->nullable()->unique()->after('pedido_id')
                ->constrained('ordenes_servicio')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_servicio_id');
        });
    }
};
