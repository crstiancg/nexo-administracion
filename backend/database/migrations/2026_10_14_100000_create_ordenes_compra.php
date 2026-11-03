<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pedido de mercadería a un proveedor (Sika, …). No mueve stock: entra
        // recién cuando llega y se registra la compra que nace de ella.
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            // "OC-000123": se completa con el id apenas se crea.
            $table->string('codigo', 12)->nullable()->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            // La sede que pide (y donde se recibe).
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            // pendiente → enviada → recibida | anulada
            $table->string('estado', 10)->index();
            $table->date('fecha_entrega')->nullable();
            // Forma de pago, lugar de entrega…: sale impreso.
            $table->string('condiciones', 500)->nullable();
            $table->string('observacion', 500)->nullable();
            // Estimado: suma de las líneas con costo (el real es el de la compra).
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('op_gravada', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            // La compra con la que se recibió.
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enviada_at')->nullable();
            $table->timestamp('anulada_at')->nullable();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('orden_compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('variantes')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3);
            // Opcional: el precio del proveedor puede no saberse al pedir.
            $table->decimal('costo_unitario', 10, 2)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);

            $table->unique(['orden_compra_id', 'variante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_compra_items');
        Schema::dropIfExists('ordenes_compra');
    }
};
