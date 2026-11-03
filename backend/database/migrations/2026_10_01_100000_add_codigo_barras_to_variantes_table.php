<?php

use App\Support\Ean13;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            // Nullable sólo por el instante entre el INSERT y el evento
            // `created` del modelo, que lo arma con el id ya asignado.
            $table->string('codigo_barras', 13)->nullable()->unique()->after('sku');
        });

        // Las variantes que ya existían reciben su código igual que las nuevas.
        DB::table('variantes')->select('id')->orderBy('id')->lazyById()->each(
            fn ($variante) => DB::table('variantes')
                ->where('id', $variante->id)
                ->update(['codigo_barras' => Ean13::paraVariante($variante->id)]),
        );
    }

    public function down(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            $table->dropUnique(['codigo_barras']);
            $table->dropColumn('codigo_barras');
        });
    }
};
