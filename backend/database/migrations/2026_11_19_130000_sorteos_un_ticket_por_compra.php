<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sorteos: cada venta u orden de servicio da 1 ticket (ya no "1 por cada
 * S/ X"), y los tickets también se venden sueltos al precio que se elija
 * (`precio_ticket` es sólo el sugerido). `vendido`: el ticket se pagó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sorteos', function (Blueprint $table) {
            $table->decimal('precio_ticket', 12, 2)->nullable()->after('termina_el');
        });
        Schema::table('sorteos', function (Blueprint $table) {
            $table->dropColumn(['monto_por_ticket', 'max_tickets_por_compra']);
        });

        Schema::table('sorteo_tickets', function (Blueprint $table) {
            $table->boolean('vendido')->default(false)->after('sede_id');
        });
    }

    public function down(): void
    {
        Schema::table('sorteo_tickets', fn (Blueprint $table) => $table->dropColumn('vendido'));
        Schema::table('sorteos', function (Blueprint $table) {
            $table->decimal('monto_por_ticket', 12, 2)->default(100);
            $table->unsignedInteger('max_tickets_por_compra')->nullable();
        });
        Schema::table('sorteos', fn (Blueprint $table) => $table->dropColumn('precio_ticket'));
    }
};
