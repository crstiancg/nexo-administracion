<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Números de serie (laptops, celulares, impresoras): cada unidad con su
 * serie, de qué compra entró, a quién se vendió y hasta cuándo tiene
 * garantía. Se registran junto a cada movimiento de inventario
 * (App\Services\Series), aparte de los lotes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('maneja_series')->default(false)->after('maneja_lotes');
            // Garantía del producto vendido (para la consulta por serie).
            $table->unsignedSmallInteger('garantia_meses')->nullable()->after('maneja_series');
        });

        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('variantes')->restrictOnDelete();
            $table->string('numero', 100);
            // en_stock (en `sede_id`) | vendida | baja (merma, devolución al proveedor…)
            $table->string('estado', 10)->index();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->timestamps();

            $table->unique(['variante_id', 'numero']);
            $table->index('numero');
        });

        // La historia de cada serie: los movimientos en los que se movió.
        Schema::create('movimiento_serie', function (Blueprint $table) {
            $table->foreignId('movimiento_inventario_id')->constrained('movimiento_inventarios')->cascadeOnDelete();
            $table->foreignId('serie_id')->constrained('series')->cascadeOnDelete();
            $table->primary(['movimiento_inventario_id', 'serie_id']);
        });

        // Las series que el vendedor eligió para el ítem (salen al confirmar).
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->json('series')->nullable()->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->dropColumn('series');
        });
        Schema::dropIfExists('movimiento_serie');
        Schema::dropIfExists('series');
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['maneja_series', 'garantia_meses']);
        });
    }
};
