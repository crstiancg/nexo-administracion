<?php

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una oferta pasa de "un producto o una categoría" a una LISTA de destinos:
 * productos completos, variantes puntuales (el Sikaflex gris en cartucho) y/o categorías.
 * Polimórfica, como `archivos`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofertables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oferta_id')->constrained('ofertas')->cascadeOnDelete();
            // Producto | Variante | Categoria
            $table->morphs('ofertable');

            $table->unique(['oferta_id', 'ofertable_type', 'ofertable_id']);
        });

        // Las ofertas que ya existían conservan su destino.
        foreach (DB::table('ofertas')->get(['id', 'producto_id', 'categoria_id']) as $oferta) {
            [$tipo, $id] = $oferta->producto_id
                ? [Producto::class, $oferta->producto_id]
                : [Categoria::class, $oferta->categoria_id];

            if ($id) {
                DB::table('ofertables')->insert([
                    'oferta_id' => $oferta->id,
                    'ofertable_type' => $tipo,
                    'ofertable_id' => $id,
                ]);
            }
        }

        Schema::table('ofertas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_id');
            $table->dropConstrainedForeignId('categoria_id');
        });
    }

    public function down(): void
    {
        Schema::table('ofertas', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('nombre')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('categoria_id')->nullable()->after('producto_id')->constrained('categorias')->cascadeOnDelete();
        });

        // Vuelve el primer destino de cada oferta (lo demás no entra en el
        // esquema viejo).
        foreach (DB::table('ofertables')->orderBy('id')->get()->groupBy('oferta_id') as $ofertaId => $destinos) {
            $primero = $destinos->first(fn ($d) => in_array($d->ofertable_type, [Producto::class, Categoria::class], true));
            if ($primero) {
                DB::table('ofertas')->where('id', $ofertaId)->update([
                    $primero->ofertable_type === Producto::class ? 'producto_id' : 'categoria_id' => $primero->ofertable_id,
                ]);
            }
        }

        Schema::dropIfExists('ofertables');
    }
};
