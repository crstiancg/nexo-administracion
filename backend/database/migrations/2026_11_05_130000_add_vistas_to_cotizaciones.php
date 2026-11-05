<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuántas veces el cliente abrió la proforma pública y cuándo fue la última:
 * el vendedor sabe a quién llamar primero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->unsignedInteger('vistas')->default(0)->after('token');
            $table->timestamp('vista_at')->nullable()->after('vistas');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['vistas', 'vista_at']);
        });
    }
};
