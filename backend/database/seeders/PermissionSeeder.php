<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Permisos del rol Vendedor (los implícitos de config/permisos.php se
     * suman solos: con ventas.store ya busca presentaciones, ve el catálogo
     * y la ficha del POS).
     */
    public const VENDEDOR = [
        // Punto de venta
        'ventas.store', 'ventas.catalogo', 'ventas.ficha',
        // Caja diaria (se abre y se cierra en el POS)
        'cajas.actual', 'cajas.abrir', 'cajas.cerrar', 'cajas.movimientos',
        // Clientes (alta rápida desde el POS, consulta RENIEC/SUNAT)
        'clientes.index', 'clientes.show', 'clientes.store', 'clientes.update', 'clientes.consultar-documento',
        // Pedidos: tomar, cobrar, confirmar y entregar (no cancelar ni devolver)
        'pedidos.index', 'pedidos.show', 'pedidos.store', 'pedidos.update', 'pedidos.pagos', 'pedidos.confirmar', 'pedidos.entregar',
        // Cotizaciones
        'cotizaciones.index', 'cotizaciones.show', 'cotizaciones.store', 'cotizaciones.update', 'cotizaciones.convertir', 'cotizaciones.rechazar',
        // Consultar productos, stock y vencimientos (sin editar)
        'productos.index', 'inventario.lotes',
    ];

    public function run(): void
    {
        // Los permisos salen de las rutas (config/permisos.php). --prune borra
        // los que ya no son rutas, como los viejos admin-roles/admin-usuarios.
        Artisan::call('permisos:sync', ['--prune' => true]);

        $admin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'api']);

        // El Administrador tiene todos los permisos del sistema; se
        // re-sincroniza al sembrar para que reciba los de rutas nuevas.
        $admin->syncPermissions(Permission::where('guard_name', 'api')->get());

        // Vendedor: vende en mostrador, cobra, atiende pedidos y cotizaciones.
        // No ve costos ni ganancias (sin productos.update ni reportes), no
        // anula ni devuelve dinero, y no administra catálogos ni usuarios.
        $vendedor = Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'api']);
        $vendedor->syncPermissions(Permission::where('guard_name', 'api')->whereIn('name', self::VENDEDOR)->get());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
