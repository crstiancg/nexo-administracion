<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El saldo de un pedido (venta u orden de servicio) financiado en
        // cuotas. Los pagos siguen siendo pagos del pedido (entran a la caja):
        // se aplican a las cuotas de la más antigua a la más nueva.
        Schema::create('creditos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->unique()->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            // Lo financiado (el saldo al otorgarlo) y lo que ya estaba pagado
            // entonces: lo que se pague después cubre las cuotas.
            $table->decimal('monto', 12, 2);
            $table->decimal('pagado_inicial', 12, 2)->default(0);
            $table->string('frecuencia', 12); // semanal | quincenal | mensual
            $table->unsignedSmallInteger('numero_cuotas');
            $table->string('observacion', 250)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('credito_cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->date('vence')->index();
            $table->decimal('monto', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credito_cuotas');
        Schema::dropIfExists('creditos');
    }
};
