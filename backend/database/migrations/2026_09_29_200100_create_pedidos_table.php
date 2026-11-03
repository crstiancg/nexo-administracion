<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            // "P-000123": se completa con el id apenas se crea.
            $table->string('codigo', 12)->nullable()->unique();
            // null = "Cliente varios" (venta rápida de mostrador).
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            // La sede que vende: de su stock sale la mercadería.
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            // pendiente → confirmado → entregado | cancelado
            $table->string('estado', 12)->index();
            $table->string('canal', 12);
            // Calculados en el servidor a partir de los ítems: nunca se
            // confía en el total que manda el navegador.
            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('observacion', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmado_at')->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamp('cancelado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pedido_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('variantes')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3);
            // Precio y costo CONGELADOS al momento de la venta: si mañana
            // cambia el precio del producto, este pedido no cambia. El costo
            // se toma al confirmar (costo promedio de ese momento).
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('costo_unitario', 12, 4)->nullable();
            $table->decimal('subtotal', 12, 2);

            $table->unique(['pedido_id', 'variante_id']);
        });

        Schema::table('movimiento_inventarios', function (Blueprint $table) {
            // La venta o la devolución que originó el movimiento.
            $table->foreignId('pedido_id')->nullable()->after('variante_id')->constrained('pedidos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimiento_inventarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pedido_id');
        });

        Schema::dropIfExists('pedido_items');
        Schema::dropIfExists('pedidos');
    }
};
