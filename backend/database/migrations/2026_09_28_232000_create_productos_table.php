<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('marca_id')->constrained('marcas')->restrictOnDelete();
            $table->text('descripcion')->nullable();
            // Precio base: lo usan las variantes que no tienen precio propio.
            $table->decimal('precio', 10, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            // Cómo se vende: "Cartucho 300 ml", "Balde 4 gl", "Bolsa 25 kg".
            $table->string('presentacion', 60);
            $table->foreignId('unidad_id')->constrained('unidades')->restrictOnDelete();
            // Opcional: sólo algunos productos vienen en colores (sellantes).
            $table->foreignId('color_id')->nullable()->constrained('colores')->restrictOnDelete();
            $table->string('sku', 40)->unique();
            // null = usa el precio base del producto.
            $table->decimal('precio', 10, 2)->nullable();
            // No se edita a mano: lo van a mover los movimientos de inventario.
            // Total de la empresa (suma de `stocks`, por sede). Decimal por las
            // unidades fraccionables.
            $table->decimal('stock', 12, 3)->default(0);
            // Por sede: por debajo de esto, la presentación aparece en "por reponer".
            $table->decimal('stock_minimo', 12, 3)->default(0);
            $table->timestamps();

            // Con color null MySQL no lo hace cumplir: también lo valida
            // StoreProductoRequest.
            $table->unique(['producto_id', 'presentacion', 'color_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variantes');
        Schema::dropIfExists('productos');
    }
};
