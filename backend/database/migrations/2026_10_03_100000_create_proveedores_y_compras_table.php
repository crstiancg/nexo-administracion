<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->char('ruc', 11)->unique();
            $table->string('razon_social', 150);
            $table->string('contacto', 100)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('razon_social');
        });

        // Un documento de compra. Registrarla ES la entrada al inventario de
        // la sede (App\Services\Compras): no hay compra "pendiente" que
        // todavía no movió stock. Anularla saca la mercadería otra vez.
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            // "C-000123": se completa con el id apenas se crea.
            $table->string('codigo', 12)->nullable()->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            // factura | boleta | guia | otro
            $table->string('tipo_documento', 10);
            $table->string('numero_documento', 30)->nullable();
            $table->date('fecha');
            $table->decimal('total', 12, 2);
            $table->string('observacion', 500)->nullable();
            $table->string('estado', 10)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulada_at')->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('variantes')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->decimal('costo_unitario', 10, 2);
            $table->decimal('subtotal', 12, 2);
            // Sólo productos con lotes.
            $table->string('lote', 40)->nullable();
            $table->date('vence_at')->nullable();

            $table->unique(['compra_id', 'variante_id']);
        });

        Schema::table('movimiento_inventarios', function (Blueprint $table) {
            // La compra que originó el movimiento (su entrada o su anulación).
            $table->foreignId('compra_id')->nullable()->after('pedido_id')->constrained('compras')->restrictOnDelete();
        });

        // Presupuesto para un cliente (constructoras, ferreterías). No mueve
        // stock: al aceptarla se convierte en un pedido pendiente.
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            // "COT-000123": se completa con el id apenas se crea.
            $table->string('codigo', 12)->nullable()->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            // pendiente → convertida | rechazada. "Vencida" no se guarda: es
            // una pendiente con valida_hasta en el pasado.
            $table->string('estado', 12)->index();
            $table->date('valida_hasta');
            // Forma de pago, plazo de entrega, etc.: sale impreso.
            $table->string('condiciones', 500)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cotizacion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('variantes')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 12, 2);

            $table->unique(['cotizacion_id', 'variante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_items');
        Schema::dropIfExists('cotizaciones');

        Schema::table('movimiento_inventarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('compra_id');
        });

        Schema::dropIfExists('compra_items');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('proveedores');
    }
};
