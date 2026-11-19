<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Basura tecnológica evitada: cada tipo de equipo puede tener cuántos kilos
 * pesa (opcional) y la orden recuerda si se entregó reparada (salió del
 * estado "reparado" al entregarse). Lo reparado y entregado ya no es basura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_equipo', function (Blueprint $table) {
            $table->decimal('kg_basura', 8, 2)->nullable()->after('checklist');
        });

        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->boolean('entregado_reparado')->default(false)->after('entregado_at');
        });

        // Las ya entregadas: reparada si el estado anterior al "entregado"
        // del historial fue "reparado".
        DB::table('ordenes_servicio')->whereNotNull('entregado_at')->orderBy('id')->each(function ($orden) {
            $estados = DB::table('orden_servicio_movimientos')
                ->where('orden_servicio_id', $orden->id)
                ->orderBy('created_at')->orderBy('id')
                ->pluck('estado')->all();
            $entrega = array_search('entregado', $estados, true);
            if ($entrega > 0 && $estados[$entrega - 1] === 'reparado') {
                DB::table('ordenes_servicio')->where('id', $orden->id)->update(['entregado_reparado' => true]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', fn (Blueprint $table) => $table->dropColumn('entregado_reparado'));
        Schema::table('tipos_equipo', fn (Blueprint $table) => $table->dropColumn('kg_basura'));
    }
};
