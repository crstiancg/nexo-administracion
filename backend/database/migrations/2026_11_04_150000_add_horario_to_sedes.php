<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            // Texto libre: sale en la proforma ("Lun-Sáb 9:00 a 19:00").
            $table->string('horario', 150)->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropColumn('horario');
        });
    }
};
