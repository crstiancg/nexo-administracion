<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lo que el dueño ajusta desde el sistema: datos de la empresa, logo,
        // textos de los documentos y mensajes de WhatsApp. Lo que no se
        // configuró usa el valor por defecto (config/empresa.php).
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 60)->unique();
            $table->json('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
