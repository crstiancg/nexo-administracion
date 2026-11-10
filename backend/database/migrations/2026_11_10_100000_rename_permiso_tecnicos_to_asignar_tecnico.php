<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * La ruta de la lista de técnicos pasa a llamarse ordenes-servicio.asignar-tecnico:
 * además de listar, da permiso para poner a otro usuario a cargo de una orden.
 * Se renombra la fila (no se crea otra) para que los roles que ya la tenían,
 * como el Administrador, la conserven.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->renombrar('ordenes-servicio.tecnicos', 'ordenes-servicio.asignar-tecnico', 'Órdenes de servicio · Asignar técnico a cargo (a otro usuario)');
    }

    public function down(): void
    {
        $this->renombrar('ordenes-servicio.asignar-tecnico', 'ordenes-servicio.tecnicos', 'Órdenes de servicio · Ver técnicos');
    }

    private function renombrar(string $de, string $a, string $descripcion): void
    {
        $tabla = config('permission.table_names.permissions', 'permissions');

        if (DB::table($tabla)->where('name', $a)->where('guard_name', 'api')->exists()) {
            return;
        }

        DB::table($tabla)->where('name', $de)->where('guard_name', 'api')
            ->update(['name' => $a, 'description' => $descripcion]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
