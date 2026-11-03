<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archivos', function (Blueprint $table) {
            // WebP chico para listados y galerías; el original sólo al ampliar.
            // null = no es imagen (o se subió antes de generar miniaturas).
            $table->string('miniatura')->nullable()->after('ruta');
            // Para que el <img> reserve su lugar y la página no salte al cargar.
            $table->unsignedSmallInteger('ancho')->nullable()->after('tamano');
            $table->unsignedSmallInteger('alto')->nullable()->after('ancho');
        });
    }

    public function down(): void
    {
        Schema::table('archivos', function (Blueprint $table) {
            $table->dropColumn(['miniatura', 'ancho', 'alto']);
        });
    }
};
