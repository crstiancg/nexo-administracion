<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            // null = categoría raíz. restrict: una categoría con subcategorías
            // no se borra (el controller lo avisa antes con un 409).
            $table->foreignId('parent_id')->nullable()->constrained('categorias')->restrictOnDelete();
            $table->string('nombre', 60);
            $table->timestamps();

            // Único entre hermanos ("Niños › Polos" y "Niñas › Polos" valen).
            // Con parent_id null MySQL no lo hace cumplir en las raíces: eso
            // (y la comparación sin mayúsculas) lo valida StoreCategoriaRequest.
            $table->unique(['parent_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
