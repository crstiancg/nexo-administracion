<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una cotización puede ofrecer lo que la tienda no tiene: ítems "libres"
 * (sin variante, con su descripción) que se consiguen por encargo. La foto
 * opcional de cada ítem va en `archivos` (polimórfico), no acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table) {
            $table->dropForeign(['variante_id']);
        });

        Schema::table('cotizacion_items', function (Blueprint $table) {
            $table->foreignId('variante_id')->nullable()->change();
            $table->foreign('variante_id')->references('id')->on('variantes')->restrictOnDelete();
            // Sólo en los libres: los del catálogo se describen con su variante.
            $table->string('descripcion', 200)->nullable()->after('variante_id');
            $table->string('unidad', 20)->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        DB::table('cotizacion_items')->whereNull('variante_id')->delete();

        Schema::table('cotizacion_items', function (Blueprint $table) {
            $table->dropForeign(['variante_id']);
            $table->dropColumn(['descripcion', 'unidad']);
        });

        Schema::table('cotizacion_items', function (Blueprint $table) {
            $table->foreignId('variante_id')->nullable(false)->change();
            $table->foreign('variante_id')->references('id')->on('variantes')->restrictOnDelete();
        });
    }
};
