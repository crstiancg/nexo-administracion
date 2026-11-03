<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            // Costo promedio ponderado: lo recalcula cada entrada. 4 decimales
            // para no acumular redondeo compra tras compra.
            $table->decimal('costo_promedio', 12, 4)->nullable()->after('stock');
        });

        // Libro de inventario: INMUTABLE. Un error se corrige con otro
        // movimiento, nunca editando ni borrando (ver MovimientoInventario).
        Schema::create('movimiento_inventarios', function (Blueprint $table) {
            $table->id();
            // Las líneas de un mismo documento (una factura con 10 ítems)
            // comparten grupo.
            $table->uuid('grupo')->index();
            // restrict: una variante con historial no se borra.
            $table->foreignId('variante_id')->constrained('variantes')->restrictOnDelete();
            // La sede cuyo stock se movió.
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            // En un traslado, la otra sede (a dónde fue o de dónde vino).
            $table->foreignId('sede_relacionada_id')->nullable()->constrained('sedes')->restrictOnDelete();
            $table->string('tipo', 10);
            // Con signo: +10 entra, -2 sale.
            $table->decimal('cantidad', 12, 3);
            // El stock de la sede justo después de este movimiento: el
            // historial se lee solo, sin recalcular desde el principio.
            $table->decimal('stock_resultante', 12, 3);
            $table->decimal('costo_unitario', 10, 2)->nullable();
            $table->string('motivo', 30)->nullable();
            $table->string('referencia', 60)->nullable();
            $table->string('observacion', 500)->nullable();
            // nullOnDelete: si se borra el usuario, el historial queda.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['variante_id', 'id']);
            $table->index(['sede_id', 'id']);
            $table->index(['tipo', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimiento_inventarios');

        Schema::table('variantes', function (Blueprint $table) {
            $table->dropColumn('costo_promedio');
        });
    }
};
