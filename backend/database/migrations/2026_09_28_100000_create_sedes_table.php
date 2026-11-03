<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tiendas / almacenes (Lima, Arequipa...). Cada una tiene su stock,
        // su caja y sus ventas.
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->string('direccion', 200)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // La sede en la que opera: el POS, la caja y los movimientos de
            // inventario del usuario son de esta sede. null = todavía sin
            // asignar (no puede vender ni mover stock).
            $table->foreignId('sede_id')->nullable()->after('active')->constrained('sedes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sede_id');
        });

        Schema::dropIfExists('sedes');
    }
};
