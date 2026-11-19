<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sorteos para fidelizar: cada compra de un cliente registrado le da tickets
 * (1 por cada `monto_por_ticket` soles) mientras el sorteo está abierto. Al
 * cierre se sortean los premios en vivo: cada ticket es una chance.
 *
 * - sede_sorteo: las sedes que participan (sin filas = todas).
 * - sorteo_tickets: numerados por sorteo; nacen de una venta entregada y se
 *   anulan si la venta se anula.
 * - sorteo_premios: en orden (1.°, 2.°…), con el ticket ganador y los
 *   ganadores descartados (no se presentó, etc.) en `descartes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sorteos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->text('bases')->nullable();
            // mensual | trimestral | otro: sólo para mostrar y filtrar.
            $table->string('periodo', 20)->default('mensual');
            $table->date('inicia_el');
            $table->date('termina_el');
            $table->decimal('monto_por_ticket', 12, 2);
            $table->unsignedInteger('max_tickets_por_compra')->nullable();
            $table->boolean('un_premio_por_cliente')->default(true);
            // abierto → sorteando (ya salió un premio: no entran más tickets)
            // → sorteado (salieron todos) | cancelado.
            $table->string('estado', 20)->default('abierto');
            $table->timestamp('sorteado_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'inicia_el', 'termina_el']);
        });

        Schema::create('sede_sorteo', function (Blueprint $table) {
            $table->foreignId('sorteo_id')->constrained('sorteos')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            $table->primary(['sorteo_id', 'sede_id']);
        });

        Schema::create('sorteo_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sorteo_id')->constrained('sorteos')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->timestamps();

            $table->unique(['sorteo_id', 'numero']);
            $table->index(['sorteo_id', 'cliente_id']);
            $table->index('pedido_id');
        });

        Schema::create('sorteo_premios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sorteo_id')->constrained('sorteos')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('descripcion', 150);
            $table->foreignId('ticket_id')->nullable()->constrained('sorteo_tickets')->nullOnDelete();
            $table->timestamp('sorteado_at')->nullable();
            $table->foreignId('sorteado_por')->nullable()->constrained('users')->nullOnDelete();
            // Tickets en juego cuando salió (para el acta).
            $table->unsignedInteger('participantes')->nullable();
            // [{ticket_id, numero, cliente, motivo, user, at}]
            $table->json('descartes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sorteo_premios');
        Schema::dropIfExists('sorteo_tickets');
        Schema::dropIfExists('sede_sorteo');
        Schema::dropIfExists('sorteos');
    }
};
