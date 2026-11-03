<?php

namespace Database\Seeders;

use App\Models\Sede;
use Illuminate\Database\Seeder;

/**
 * La primera sede, para que el sistema funcione desde el primer día. Las
 * demás (Arequipa, etc.) se crean desde la pantalla de Sedes.
 */
class SedeSeeder extends Seeder
{
    public function run(): void
    {
        Sede::firstOrCreate(['nombre' => 'Sede principal'], ['activo' => true]);
    }
}
