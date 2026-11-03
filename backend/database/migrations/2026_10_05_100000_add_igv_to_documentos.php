<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Desglose del IGV (incluido en el total) en pedidos, cotizaciones y
 * compras. Se guarda y no se recalcula: si la tasa cambia, lo emitido queda
 * como se emitió.
 */
return new class extends Migration
{
    private const TABLAS = ['pedidos', 'cotizaciones', 'compras'];

    public function up(): void
    {
        $tasa = 1 + (float) config('app.igv', 0.18);

        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->decimal('op_gravada', 12, 2)->default(0)->after('total');
                $table->decimal('igv', 12, 2)->default(0)->after('op_gravada');
            });

            // Lo ya registrado: misma cuenta que App\Support\Igv::desglosar.
            DB::table($tabla)->update(['op_gravada' => DB::raw("ROUND(total / {$tasa}, 2)")]);
            DB::table($tabla)->update(['igv' => DB::raw('total - op_gravada')]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropColumn(['op_gravada', 'igv']));
        }
    }
};
