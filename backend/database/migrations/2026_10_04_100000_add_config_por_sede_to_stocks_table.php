<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo es común (mismo producto, SKU y código de barras en todas las
 * sedes) pero cada sede decide qué presentaciones vende, a qué precio y con
 * qué stock mínimo. Esa configuración vive en `stocks`, la fila
 * presentación × sede que ya tenía la cantidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            // false = la sede no la vende (no aparece en su POS ni en sus
            // buscadores). El stock que quede igual se puede sacar o trasladar.
            $table->boolean('activo')->default(true)->after('cantidad');
            // null = el precio general de la presentación (o del producto).
            $table->decimal('precio', 10, 2)->nullable()->after('activo');
            $table->decimal('stock_minimo', 12, 3)->default(0)->after('precio');
        });

        // Lo que ya existía se vende en todas las sedes activas, con el mínimo
        // que tenía la presentación: nada desaparece de ningún POS.
        DB::statement('
            INSERT INTO stocks (variante_id, sede_id, cantidad, activo, stock_minimo)
            SELECT variantes.id, sedes.id, 0, 1, variantes.stock_minimo
            FROM variantes CROSS JOIN sedes
            WHERE sedes.activo = 1
              AND NOT EXISTS (SELECT 1 FROM stocks s WHERE s.variante_id = variantes.id AND s.sede_id = sedes.id)
        ');
        DB::table('stocks')
            ->join('variantes', 'variantes.id', '=', 'stocks.variante_id')
            ->update(['stocks.stock_minimo' => DB::raw('variantes.stock_minimo')]);

        Schema::table('variantes', function (Blueprint $table) {
            $table->dropColumn('stock_minimo');
        });
    }

    public function down(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            $table->decimal('stock_minimo', 12, 3)->default(0)->after('stock');
        });

        // El mínimo de la sede más exigente vuelve a la presentación.
        DB::statement('
            UPDATE variantes SET stock_minimo = COALESCE(
                (SELECT MAX(stocks.stock_minimo) FROM stocks WHERE stocks.variante_id = variantes.id), 0)
        ');

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn(['activo', 'precio', 'stock_minimo']);
        });
    }
};
