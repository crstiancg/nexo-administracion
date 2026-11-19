<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PC armada a precio de llegada: los componentes van al costo y encima se
 * pone una ganancia POR EQUIPO, en soles (`monto`) o en % del costo
 * (`porcentaje`). `ganancia_equipo` es lo que resultó en soles (lo calcula
 * App\Services\Cotizaciones): con él se reparte en los precios de la
 * proforma y del pedido, para que el cliente nunca vea el costo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->decimal('ganancia', 12, 2)->default(0)->after('equipos');
            $table->string('ganancia_tipo', 10)->default('monto')->after('ganancia');
            $table->decimal('ganancia_equipo', 12, 2)->default(0)->after('ganancia_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['ganancia', 'ganancia_tipo', 'ganancia_equipo']);
        });
    }
};
