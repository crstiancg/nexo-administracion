<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "10 PCs iguales" (colegios, municipalidades): los ítems de una PC armada
 * son los de UN equipo y el total se multiplica por `equipos`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->unsignedSmallInteger('equipos')->default(1)->after('mostrar_precios');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn('equipos');
        });
    }
};
