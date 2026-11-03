<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofertas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            // A qué se aplica: un producto (todas sus variantes) o una
            // categoría (con o sin sus subcategorías). Uno de los dos.
            $table->foreignId('producto_id')->nullable()->constrained('productos')->cascadeOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->cascadeOnDelete();
            $table->boolean('incluye_subcategorias')->default(true);
            // porcentaje: `valor` es el % de descuento. precio_fijo: `valor`
            // es el precio final (sólo para un producto).
            $table->string('tipo', 12);
            $table->decimal('valor', 10, 2);
            // En UTC, como el resto de la base: el form manda la fecha con su
            // zona horaria y se convierte acá.
            $table->timestamp('inicia_at');
            $table->timestamp('termina_at');
            // Pausar sin borrar ni tocar las fechas.
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['activa', 'inicia_at', 'termina_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofertas');
    }
};
