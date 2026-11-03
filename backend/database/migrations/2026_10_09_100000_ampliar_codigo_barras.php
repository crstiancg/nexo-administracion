<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El código de barras lo registra el usuario y no siempre es un EAN-13:
 * sin reglas de formato ni de largo (255 = tope práctico de la columna).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            $table->string('codigo_barras', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            $table->string('codigo_barras', 13)->nullable()->change();
        });
    }
};
