<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            // Siempre "#RRGGBB" en mayúsculas: lo normaliza StoreColorRequest,
            // así el unique compara el mismo color escrito de formas distintas.
            $table->char('hexadecimal', 7)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colores');
    }
};
