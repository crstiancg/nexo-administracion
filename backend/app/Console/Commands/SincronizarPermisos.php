<?php

namespace App\Console\Commands;

use App\Support\RutasProtegidas;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea un permiso por cada ruta protegida por `autorizar.ruta`. Así los
 * nombres nunca se tipean a mano: un typo sería un permiso que no habilita
 * nada y nadie se entera. Lo mismo se puede hacer desde la pantalla de
 * Permisos ("Nuevo permiso"), eligiendo entre las rutas disponibles.
 */
class SincronizarPermisos extends Command
{
    protected $signature = 'permisos:sync
                            {--prune : Borra los permisos que ya no corresponden a ninguna ruta}';

    protected $description = 'Crea los permisos a partir de los nombres de las rutas protegidas';

    public function handle(RutasProtegidas $rutas): int
    {
        $nombres = $rutas->todas()->keys();

        $creados = 0;
        foreach ($nombres as $nombre) {
            // firstOrCreate y no updateOrCreate: la descripción que editó un
            // administrador desde la pantalla no se pisa.
            $permiso = Permission::firstOrCreate(
                ['name' => $nombre, 'guard_name' => 'api'],
                ['description' => $rutas->describir($nombre)],
            );
            $creados += $permiso->wasRecentlyCreated ? 1 : 0;
        }

        $borrados = 0;
        if ($this->option('prune')) {
            $borrados = Permission::where('guard_name', 'api')
                ->whereNotIn('name', $nombres)
                ->get()
                ->each->delete()
                ->count();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->components->info("Permisos: {$nombres->count()} rutas, {$creados} nuevos, {$borrados} borrados.");

        return self::SUCCESS;
    }
}
