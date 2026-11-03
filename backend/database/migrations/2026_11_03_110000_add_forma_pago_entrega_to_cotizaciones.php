<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lo que la proforma impresa muestra aparte de las condiciones: cómo se
        // paga ("Contado", "50% de adelanto…") y cómo se entrega. La validez
        // ya es `valida_hasta`.
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('forma_pago', 100)->nullable()->after('valida_hasta');
            $table->string('entrega', 250)->nullable()->after('forma_pago');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['forma_pago', 'entrega']);
        });
    }
};
