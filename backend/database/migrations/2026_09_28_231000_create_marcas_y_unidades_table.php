<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sika, Chema, Z Aditivos...: cada producto es de una marca.
        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Unidad en la que se vende y se cuenta el stock de una presentación
        // (Bolsa, Galón, Balde, Kg, Metro...).
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 40)->unique();
            // Lo que sale en el ticket y en las etiquetas: "BLS", "GL", "KG".
            $table->string('abreviatura', 10)->unique();
            // Kg, metro, m²: se pueden vender fracciones. Bolsa, balde: no.
            $table->boolean('fraccionable')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades');
        Schema::dropIfExists('marcas');
    }
};
