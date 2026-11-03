<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock de cada presentación en cada sede. `variantes.stock` queda como
     * el total de la empresa (la suma de estas filas). Los dos los mueve sólo
     * App\Services\Inventario, en la misma transacción.
     */
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('variantes')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            // Decimal: se venden fracciones de las unidades fraccionables (kg, m).
            $table->decimal('cantidad', 12, 3)->default(0);

            $table->unique(['variante_id', 'sede_id']);
            $table->index(['sede_id', 'variante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
