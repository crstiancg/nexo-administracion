<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            // Código del establecimiento en la ficha RUC: 0000 = domicilio
            // fiscal, 0001… = anexos. Va en cada comprobante que emite la sede.
            $table->char('codigo_sunat', 4)->default('0000')->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropColumn('codigo_sunat');
        });
    }
};
