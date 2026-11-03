<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una línea de pedido puede ser un servicio (mano de obra de una orden
        // de servicio): sin presentación, con descripción, y no mueve stock.
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->foreignId('variante_id')->nullable()->change();
            $table->string('descripcion', 250)->nullable()->after('variante_id');
        });

        // Laptop, Celular, Impresora…: cada tipo trae su propia lista de
        // revisión al recibir el equipo (enciende, pantalla, bisagras…).
        Schema::create('tipos_equipo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->json('checklist')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('ordenes_servicio', function (Blueprint $table) {
            $table->id();
            // "OS-000123": se completa con el id apenas se crea.
            $table->string('codigo', 12)->nullable()->unique();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // El equipo.
            $table->foreignId('tipo_equipo_id')->constrained('tipos_equipo')->restrictOnDelete();
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->restrictOnDelete();
            $table->string('modelo', 80)->nullable();
            $table->string('serie', 60)->nullable()->index();
            $table->string('accesorios', 250)->nullable();
            // ninguno | pin | contrasena | patron. El valor va cifrado.
            $table->string('seguridad_tipo', 12)->default('ninguno');
            $table->text('seguridad_valor')->nullable();
            // { "Enciende": true, "Golpes": false, … } al momento de recibirlo.
            $table->json('checklist')->nullable();

            // El trabajo.
            $table->string('falla', 1000);
            $table->text('diagnostico')->nullable();
            $table->string('observacion', 500)->nullable();
            $table->boolean('es_garantia')->default(false);
            $table->unsignedSmallInteger('garantia_dias')->default(0);
            $table->decimal('mano_obra', 12, 2)->default(0);

            $table->string('estado', 20)->index();
            // El cobro vive en un pedido (repuestos + mano de obra): abonos,
            // caja y comprobante electrónico salen de ahí.
            $table->foreignId('pedido_id')->nullable()->unique()->constrained('pedidos')->restrictOnDelete();
            // Para la página pública de seguimiento (QR del ticket).
            $table->string('token', 40)->unique();

            $table->timestamp('recibido_at');
            $table->timestamp('entregado_at')->nullable();
            $table->timestamps();
        });

        // Historial de estados: quién la movió, cuándo y por qué.
        Schema::create('orden_servicio_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_servicio_id')->constrained('ordenes_servicio')->cascadeOnDelete();
            $table->string('estado', 20);
            $table->string('nota', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_servicio_movimientos');
        Schema::dropIfExists('ordenes_servicio');
        Schema::dropIfExists('tipos_equipo');

        Schema::table('pedido_items', function (Blueprint $table) {
            $table->dropColumn('descripcion');
            $table->foreignId('variante_id')->nullable(false)->change();
        });
    }
};
