<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El porcentaje que gana cada empleado: sobre la mano de obra de las
        // órdenes que entrega (técnico) y sobre lo que vende (vendedor).
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('comision_reparacion', 5, 2)->default(0);
            $table->decimal('comision_venta', 5, 2)->default(0);
        });

        // Lo pagado de comisiones, por período: un período no se paga dos
        // veces. El pago es además un gasto (concepto "Comisiones").
        Schema::create('comision_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('desde');
            $table->date('hasta');
            $table->decimal('monto', 12, 2);
            $table->foreignId('movimiento_financiero_id')->nullable()->constrained('movimientos_financieros')->nullOnDelete();
            $table->foreignId('pagado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'desde', 'hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_pagos');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['comision_reparacion', 'comision_venta']);
        });
    }
};
