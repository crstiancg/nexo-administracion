<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La garantía que se ofrece ("1 año", "6 meses", "Sin garantía"…):
        // sale impresa en la proforma con la forma de pago y la entrega.
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('garantia', 100)->nullable()->after('entrega');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn('garantia');
        });
    }
};
