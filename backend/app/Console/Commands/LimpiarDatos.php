<?php

namespace App\Console\Commands;

use App\Support\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Deja el sistema en cero para empezar a operar: borra TODO lo que es
 * operación (productos, stock, ventas, caja, compras, cotizaciones,
 * clientes, proveedores, órdenes de servicio, sorteos, vales, créditos,
 * comprobantes, auditoría…) y las fotos subidas.
 *
 * Funciona al revés de una lista de borrado: se nombra lo que SE CONSERVA y
 * se vacía todo lo demás, así una tabla nueva nunca queda sin limpiar.
 * No se puede deshacer: pide confirmación (salvo --force).
 */
class LimpiarDatos extends Command
{
    protected $signature = 'nexo:limpiar-datos
        {--simular : Sólo mostrar qué se borraría, sin borrar nada}
        {--force : No pedir confirmación}';

    protected $description = 'Borra todos los datos de operación y las fotos; conserva usuarios, roles y permisos, sedes, datos de la empresa y catálogos';

    /** Lo que se conserva, con el porqué (se muestra antes de confirmar). */
    private const CONSERVAR = [
        'Usuarios, roles y permisos' => ['users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions'],
        // Cada usuario está asignado a una sede.
        'Sedes' => ['sedes'],
        // Sin ellas nadie podría iniciar sesión.
        'Sesiones y acceso' => ['oauth_clients', 'oauth_access_tokens', 'oauth_refresh_tokens', 'oauth_auth_codes', 'oauth_device_codes', 'sessions', 'password_reset_tokens'],
        'Configuración (datos de la empresa, logo, términos, mensajes de WhatsApp)' => ['configuraciones'],
        'Catálogos' => ['categorias', 'marcas', 'unidades', 'colores', 'tipos_equipo', 'conceptos_financieros'],
        // Los estados de la orden los necesita el sistema; las series siguen
        // su correlativo: un número de boleta o factura nunca se repite ante SUNAT.
        'Estados de orden y series de comprobantes' => ['estados_orden', 'comprobante_series'],
        'Interno de Laravel' => ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'],
    ];

    public function handle(): int
    {
        $conservar = array_merge(...array_values(self::CONSERVAR));
        $base = DB::getDatabaseName();
        // Sólo las tablas de la app: en MySQL su base; en Postgres su esquema.
        $esquema = match (DB::getDriverName()) {
            'pgsql' => Schema::getConnection()->getConfig('search_path') ?: 'public',
            'mysql', 'mariadb' => $base,
            default => null,
        };
        $tablas = collect(Schema::getTableListing($esquema, false))
            ->reject(fn ($t) => in_array($t, $conservar, true))
            ->sort()
            ->values()
            ->all();

        // Nunca vaciar una tabla de la que dependa algo que se conserva: en
        // Postgres el TRUNCATE … CASCADE se lo llevaría también.
        foreach ($conservar as $tabla) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            foreach (Schema::getForeignKeys($tabla) as $fk) {
                if (in_array($fk['foreign_table'], $tablas, true)) {
                    $this->error("No se limpia: «{$tabla}» (se conserva) depende de «{$fk['foreign_table']}» (se borraría). Revisa la lista CONSERVAR.");

                    return self::FAILURE;
                }
            }
        }

        $this->warn('Se van a BORRAR todos los registros de:');
        $this->table(['Tabla', 'Registros'], array_map(fn ($t) => [$t, DB::table($t)->count()], $tablas));
        $this->info('Se conservan: '.implode(' · ', array_keys(self::CONSERVAR)).'.');
        $this->warn('También se borran las fotos y archivos subidos (menos el logo de la empresa).');
        $this->line('Base: '.$base.' ('.DB::getDriverName().')');

        if ($this->option('simular')) {
            $this->info('Simulación: no se borró nada.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Borrar todo eso? No se puede deshacer', false)) {
            $this->info('Cancelado: no se borró nada.');

            return self::SUCCESS;
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tablas as $tabla) {
                // Reinicia también los contadores: los códigos vuelven a empezar (P-000001, OS-000001…).
                DB::table($tabla)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $archivos = $this->borrarArchivos();

        // Caché de consultas (DNI/RUC, ofertas, configuración) que apuntaba a lo borrado.
        Cache::flush();

        $this->info('Listo: '.count($tablas).' tablas vacías y '.$archivos.' archivos borrados. Los usuarios siguen con su sede y su rol.');

        return self::SUCCESS;
    }

    /** Fotos de productos y equipos, firmas, etc. Queda el logo de la empresa. */
    private function borrarArchivos(): int
    {
        $disco = Storage::disk('public');
        $logo = Empresa::logoRuta();

        $borrar = array_values(array_filter($disco->allFiles(), fn ($f) => $f !== $logo && ! str_starts_with(basename($f), '.')));
        $disco->delete($borrar);

        foreach ($disco->directories() as $carpeta) {
            if (! $logo || ! str_starts_with($logo, $carpeta.'/')) {
                $disco->deleteDirectory($carpeta);
            }
        }

        return count($borrar);
    }
}
