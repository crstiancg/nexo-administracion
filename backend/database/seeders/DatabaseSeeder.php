<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Sin WithoutModelEvents a propósito: los datos de demostración necesitan
 * los eventos de los modelos (el código de barras de cada presentación se
 * asigna en `created`).
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ClientTokenSeeder::class,
            PermissionSeeder::class,
            SedeSeeder::class,
            UserSeeder::class,
            CatalogoSeeder::class,
        ]);

        // Datos de demostración sólo en desarrollo: en producción se arranca
        // con la base limpia (o a mano: php artisan db:seed --class=DemoSeeder).
        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
