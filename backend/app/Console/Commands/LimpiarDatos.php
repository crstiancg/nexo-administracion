<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Deja la base lista para empezar a operar: borra productos, inventario,
 * ventas, caja, compras, clientes, etc. y conserva lo que es configuración
 * (usuarios, roles y permisos, sedes, categorías, unidades y marcas).
 * No se puede deshacer: pide confirmación (salvo --force).
 */
class LimpiarDatos extends Command
{
    protected $signature = 'nexo:limpiar-datos {--force : No pedir confirmación}';

    protected $description = 'Borra productos, inventario, ventas, caja, compras, clientes y proveedores; conserva usuarios, roles, sedes, categorías, unidades y marcas';

    /** De lo más dependiente a lo menos (igual se desactivan las FK). */
    private const TABLAS = [
        'movimiento_lotes',
        'movimiento_inventarios',
        'lotes',
        'pagos',
        'movimiento_cajas',
        'cajas',
        'pedido_items',
        'pedidos',
        'cotizacion_items',
        'cotizaciones',
        'orden_compra_items',
        'ordenes_compra',
        'compra_items',
        'compras',
        'ofertables',
        'ofertas',
        'stocks',
        'archivos',
        'variantes',
        'productos',
        'colores',
        'clientes',
        'proveedores',
    ];

    public function handle(): int
    {
        $tablas = array_values(array_filter(self::TABLAS, fn ($t) => Schema::hasTable($t)));

        $this->warn('Se van a BORRAR todos los registros de:');
        $this->table(['Tabla', 'Registros'], array_map(fn ($t) => [$t, DB::table($t)->count()], $tablas));
        $this->info('Se conservan: usuarios, roles y permisos, sedes, categorías, unidades de medida y marcas.');
        $this->line('Base: '.config('database.connections.'.config('database.default').'.database'));

        if (! $this->option('force') && ! $this->confirm('¿Borrar todo eso? No se puede deshacer', false)) {
            $this->info('Cancelado: no se borró nada.');

            return self::SUCCESS;
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tablas as $tabla) {
                DB::table($tabla)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Las fotos de los productos (ya no hay productos que las usen).
        Storage::disk('public')->deleteDirectory('productos');

        // Caché de consultas (DNI/RUC, ofertas) que apuntaba a lo borrado.
        Cache::flush();

        $this->info('Listo: base limpia. Los usuarios siguen con su sede y su rol.');

        return self::SUCCESS;
    }
}
