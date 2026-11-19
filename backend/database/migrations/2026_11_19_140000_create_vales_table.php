<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vales de fidelización del taller: se generan en una orden de servicio
 * (un % de descuento para el cliente) y se usan en su próxima orden, sobre la
 * mano de obra. No vencen; un vale se usa una sola vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 12)->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->unsignedTinyInteger('porcentaje');
            // Dónde se generó y dónde se usó (null = todavía disponible).
            $table->foreignId('orden_origen_id')->nullable()->unique()->constrained('ordenes_servicio')->nullOnDelete();
            $table->foreignId('orden_uso_id')->nullable()->unique()->constrained('ordenes_servicio')->nullOnDelete();
            $table->timestamp('usado_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cliente_id', 'orden_uso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vales');
    }
};
