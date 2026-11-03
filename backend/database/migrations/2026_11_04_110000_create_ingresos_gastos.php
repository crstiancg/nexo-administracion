<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alquiler, sueldos, luz, repuestos…: con qué se clasifica cada gasto
        // (o ingreso que no es una venta) para ver en qué se va la plata.
        Schema::create('conceptos_financieros', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10); // ingreso | gasto
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tipo', 'nombre']);
        });

        // Un gasto o un ingreso que no es venta. Si fue en efectivo y salió
        // (o entró) del cajón, además queda como movimiento de la caja.
        Schema::create('movimientos_financieros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->string('tipo', 10)->index(); // ingreso | gasto
            $table->foreignId('concepto_id')->constrained('conceptos_financieros')->restrictOnDelete();
            $table->decimal('monto', 12, 2);
            $table->string('metodo', 15);
            $table->date('fecha')->index();
            $table->string('descripcion', 250)->nullable();
            $table->string('referencia', 40)->nullable();
            $table->foreignId('movimiento_caja_id')->nullable()->constrained('movimiento_cajas')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_financieros');
        Schema::dropIfExists('conceptos_financieros');
    }
};
