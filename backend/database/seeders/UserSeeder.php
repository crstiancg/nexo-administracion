<?php

namespace Database\Seeders;

use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Sin sede no se puede vender ni mover stock: arrancan en la primera.
        $sede = Sede::query()->orderBy('id')->value('id');

        // En producción la clave del admin sale del entorno: nunca la de
        // ejemplo que está en el código.
        $clave = env('ADMIN_PASSWORD');
        if (! $clave && ! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Definí ADMIN_PASSWORD antes de sembrar en producción.');
        }

        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrador',
                'password' => $clave ?: 'nexo',
                'active' => true,
                'sede_id' => $sede,
            ],
        );
        $admin->assignRole('Administrador');

        // Usuario de prueba (clave "password"): sólo en desarrollo.
        if (app()->environment('local')) {
            $prueba = User::firstOrCreate(
                ['email' => 'password@gmail.com'],
                [
                    'name' => 'Usuario de Prueba',
                    'username' => 'password',
                    'password' => 'password',
                    'active' => true,
                    'sede_id' => $sede,
                ],
            );
            $prueba->assignRole('Administrador');
        }
    }
}
