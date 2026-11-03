<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            // Documento opcional: un cliente de WhatsApp puede dejar sólo
            // nombre y teléfono. DNI | RUC | CE.
            $table->string('tipo_documento', 3)->nullable();
            $table->string('numero_documento', 15)->nullable();
            // Nombre completo o razón social.
            $table->string('nombre', 150);
            $table->string('telefono', 20)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->timestamps();

            // Sin documento (NULL) no choca: varios clientes pueden no tenerlo.
            $table->unique(['tipo_documento', 'numero_documento']);
            $table->index('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
