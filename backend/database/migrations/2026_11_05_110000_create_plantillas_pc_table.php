<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuraciones de PC que se repiten ("PC Oficina Core i5"): se cargan en
 * una cotización nueva y se ajustan. Los ítems van en JSON (componente,
 * variante_id o descripcion, unidad, cantidad, precio_unitario).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_pc', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('garantia', 100)->nullable();
            $table->json('items');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_pc');
    }
};
