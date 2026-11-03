<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Adhesivos, aditivos, morteros: vencen. Con esto prendido cada
            // entrada pide lote y vencimiento, y las salidas descuentan del
            // lote que vence primero.
            $table->boolean('maneja_lotes')->default(false)->after('activo');
        });

        // Un lote de una presentación en una sede. En un producto con lotes,
        // la suma de sus lotes en la sede ES el stock de la sede (los mueve
        // App\Services\Inventario junto con `stocks`).
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('variantes')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            // El código impreso en el envase.
            $table->string('codigo', 40);
            $table->date('vence_at')->nullable();
            $table->decimal('cantidad', 12, 3)->default(0);
            $table->timestamps();

            // El mismo lote trasladado a otra sede es otra fila (con el mismo
            // código y vencimiento).
            $table->unique(['variante_id', 'sede_id', 'codigo']);
            $table->index(['sede_id', 'vence_at']);
        });

        // De qué lotes salió (o a cuáles entró) cada movimiento. Una venta de
        // 10 puede salir 4 de un lote y 6 del siguiente.
        Schema::create('movimiento_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_inventario_id')->constrained('movimiento_inventarios')->cascadeOnDelete();
            $table->foreignId('lote_id')->constrained('lotes')->restrictOnDelete();
            // Con el mismo signo que el movimiento.
            $table->decimal('cantidad', 12, 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimiento_lotes');
        Schema::dropIfExists('lotes');

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('maneja_lotes');
        });
    }
};
