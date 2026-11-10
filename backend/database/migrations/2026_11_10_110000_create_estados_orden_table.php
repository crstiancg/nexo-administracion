<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los estados de la orden de servicio dejan de ser fijos: se crean, renombran,
 * ordenan y ocultan desde Catálogos > Estados de orden. La orden y su
 * historial siguen guardando la `clave` (no cambia aunque cambie el nombre).
 *
 * Lo que el sistema hace con cada estado lo decide su `tipo`
 * (App\Models\EstadoOrden): dónde nace la orden, cuáles están en el taller,
 * desde cuáles se entrega y cuál es la entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_orden', function (Blueprint $table) {
            $table->id();
            // Mismo largo que ordenes_servicio.estado y su historial.
            $table->string('clave', 20)->unique();
            $table->string('nombre', 40);
            $table->string('tipo', 20)->index();
            // Ícono de Material Symbols y color del chip (positive, info…).
            $table->string('icono', 40);
            $table->string('color', 20);
            // Lo que ve el cliente en la página de seguimiento (QR del ticket).
            $table->string('mensaje_cliente', 250)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Los de siempre, con el mismo comportamiento que tenían en el código.
        $estados = [
            ['recibido', 'Recibido', 'inicial', 'move_to_inbox', 'warning', 'Recibimos su equipo. Pronto empezaremos a revisarlo.'],
            ['diagnostico', 'En diagnóstico', 'en_proceso', 'troubleshoot', 'info', 'Un técnico está revisando su equipo.'],
            ['esperando_repuesto', 'Esperando repuesto', 'en_proceso', 'hourglass_top', 'warning', 'Estamos esperando un repuesto para continuar con la reparación.'],
            ['en_reparacion', 'En reparación', 'en_proceso', 'build', 'info', 'Su equipo se está reparando.'],
            ['reparado', 'Reparado', 'listo', 'task_alt', 'positive', '¡Su equipo está listo! Puede pasar a recogerlo.'],
            ['sin_reparacion', 'Sin reparación', 'listo', 'block', 'negative', 'No fue posible reparar su equipo. Puede pasar a recogerlo.'],
            ['entregado', 'Entregado', 'entregado', 'handshake', 'positive', 'Su equipo fue entregado. ¡Gracias por su confianza!'],
            ['abandonado', 'Abandonado', 'cerrado', 'inventory', 'negative', 'Su equipo sigue en el taller. Comuníquese con nosotros para coordinar el retiro.'],
        ];

        $ahora = now();
        DB::table('estados_orden')->insert(array_map(fn ($e, $i) => [
            'clave' => $e[0],
            'nombre' => $e[1],
            'tipo' => $e[2],
            'icono' => $e[3],
            'color' => $e[4],
            'mensaje_cliente' => $e[5],
            'orden' => $i + 1,
            'activo' => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], $estados, array_keys($estados)));
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_orden');
    }
};
