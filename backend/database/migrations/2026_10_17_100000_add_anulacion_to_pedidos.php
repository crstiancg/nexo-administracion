<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anular una venta ya hecha (confirmada o entregada): vuelve el stock y la
 * plata, y queda quién, cuándo y por qué. Distinto de cancelar, que es para
 * un pedido que todavía no se vendió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->timestamp('anulado_at')->nullable()->after('cancelado_at');
            $table->foreignId('anulado_por')->nullable()->after('anulado_at')->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 255)->nullable()->after('anulado_por');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulado_por');
            $table->dropColumn(['anulado_at', 'motivo_anulacion']);
        });
    }
};
