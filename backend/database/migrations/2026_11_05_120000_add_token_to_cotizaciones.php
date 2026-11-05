<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Enlace público de la proforma ({frontend}/proforma/{token}): el cliente
 * la abre desde WhatsApp sin sesión, como el seguimiento de una orden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('token', 40)->nullable()->unique()->after('codigo');
        });

        // Las que ya existen también se pueden compartir.
        DB::table('cotizaciones')->whereNull('token')->orderBy('id')->each(function ($cotizacion) {
            DB::table('cotizaciones')->where('id', $cotizacion->id)->update(['token' => Str::random(32)]);
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn('token');
        });
    }
};
