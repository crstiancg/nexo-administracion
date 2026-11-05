<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién cambió qué y cuándo: un registro por alta, cambio o baja de los
 * modelos sensibles (precios, ventas, pagos, caja, usuarios…). No se edita
 * ni se borra desde el sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // creado | editado | borrado
            $table->string('evento', 10);
            // El modelo (Producto, Pedido…) y su id: el registro puede ya no existir.
            $table->string('modelo', 40);
            $table->unsignedBigInteger('modelo_id');
            // Cómo se llamaba al momento del cambio ("P-000012", "Laptop HP 250").
            $table->string('etiqueta', 150)->nullable();
            // {campo: [antes, después]}
            $table->json('cambios')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['modelo', 'modelo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
