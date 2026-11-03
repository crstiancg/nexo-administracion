<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La firma del cliente al dejar el equipo y al retirarlo (PNG en el
        // disco public). Las fotos van como archivos polimórficos.
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->string('firma_recepcion')->nullable()->after('checklist');
            $table->string('firma_entrega')->nullable()->after('firma_recepcion');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropColumn(['firma_recepcion', 'firma_entrega']);
        });
    }
};
