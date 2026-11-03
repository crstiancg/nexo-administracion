<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->string('estado', 10);
            // true mientras está abierta, NULL al cerrarse. El unique hace
            // que la BASE garantice una sola caja abierta por sede: dos
            // "Abrir caja" simultáneos no pueden ganar los dos (NULL no
            // choca con NULL).
            $table->boolean('abierta')->nullable();
            $table->decimal('monto_apertura', 12, 2);
            $table->foreignId('abierta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('abierta_at');
            // Arqueo, sólo de efectivo: lo esperado según el sistema contra lo
            // que se contó en el cajón.
            $table->decimal('monto_esperado', 12, 2)->nullable();
            $table->decimal('monto_contado', 12, 2)->nullable();
            $table->decimal('diferencia', 12, 2)->nullable();
            $table->string('observacion_cierre', 500)->nullable();
            $table->foreignId('cerrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();

            $table->unique(['sede_id', 'abierta']);
        });

        // Pagos de pedidos. INMUTABLES: un error se corrige con una devolución
        // (monto negativo) en la caja abierta, nunca editando.
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            // efectivo | yape | plin | transferencia | tarjeta
            $table->string('metodo', 15);
            // Lo aplicado al pedido. Negativo = devolución.
            $table->decimal('monto', 12, 2);
            // Sólo efectivo: lo que entregó el cliente y el vuelto.
            $table->decimal('recibido', 12, 2)->nullable();
            $table->decimal('vuelto', 12, 2)->nullable();
            // N° de operación (Yape, Plin, transferencia) o voucher (POS).
            $table->string('referencia', 40)->nullable();
            $table->string('motivo', 200)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['caja_id', 'metodo']);
        });

        // Ingresos y egresos de efectivo que no son ventas (retiro para el
        // banco, pago en efectivo a un proveedor…). Sin esto el arqueo nunca
        // cuadra.
        Schema::create('movimiento_cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->string('tipo', 10);
            $table->decimal('monto', 12, 2);
            $table->string('concepto', 200);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimiento_cajas');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('cajas');
    }
};
