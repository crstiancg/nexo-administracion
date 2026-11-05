<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotización de "PC armada": los ítems son los componentes de una
 * computadora (Procesador, Placa madre, RAM…) y la proforma puede mostrar
 * sólo el total, como el formato que Nexo usaba en Excel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            // normal | pc
            $table->string('tipo', 10)->default('normal')->after('codigo');
            // false = la proforma no muestra el precio de cada ítem (sólo el total).
            $table->boolean('mostrar_precios')->default(true)->after('garantia');
        });

        Schema::table('cotizacion_items', function (Blueprint $table) {
            // El casillero del armado ("Procesador", "Fuente de poder"…).
            $table->string('componente', 40)->nullable()->after('cotizacion_id');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table) {
            $table->dropColumn('componente');
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'mostrar_precios']);
        });
    }
};
