<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venta por mayor a empresas: cada presentación puede tener un precio por
 * mayor (el mismo en todas las sedes), los clientes se marcan como
 * mayoristas y cada ítem vendido recuerda si salió a ese precio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            // null = sin precio por mayor (al mayorista se le cobra el normal).
            $table->decimal('precio_mayor', 10, 2)->nullable()->after('precio');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('mayorista')->default(false)->after('nombre');
        });

        Schema::table('pedido_items', function (Blueprint $table) {
            $table->boolean('por_mayor')->default(false)->after('precio_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_items', fn (Blueprint $table) => $table->dropColumn('por_mayor'));
        Schema::table('clientes', fn (Blueprint $table) => $table->dropColumn('mayorista'));
        Schema::table('variantes', fn (Blueprint $table) => $table->dropColumn('precio_mayor'));
    }
};
