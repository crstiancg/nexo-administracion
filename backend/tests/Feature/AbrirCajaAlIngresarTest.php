<?php

namespace Tests\Feature;

use App\Models\Sede;
use App\Services\Cajas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class AbrirCajaAlIngresarTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    public function test_pide_abrir_si_la_sede_no_tiene_caja_abierta(): void
    {
        $sede = Sede::create(['nombre' => 'Matriz']);
        $user = $this->actuarCon('cajas.abrir-al-ingresar', 'cajas.abrir');
        $user->forceFill(['sede_id' => $sede->id])->save();

        $this->getJson('/api/cajas/al-ingresar')
            ->assertOk()
            ->assertJson(['abrir' => true, 'sede' => 'Matriz']);

        // Ya abierta (por él o por otro de la sede): no se pide.
        app(Cajas::class)->abrir($sede->id, 50, $user);
        $this->getJson('/api/cajas/al-ingresar')->assertJson(['abrir' => false]);
    }

    public function test_no_pide_abrir_sin_sede_o_sin_permiso_de_abrir(): void
    {
        $this->actuarCon('cajas.abrir-al-ingresar', 'cajas.abrir');
        $this->getJson('/api/cajas/al-ingresar')->assertJson(['abrir' => false, 'sede' => null]);

        $user = $this->actuarCon('cajas.abrir-al-ingresar');
        $user->forceFill(['sede_id' => Sede::create(['nombre' => 'Matriz'])->id])->save();
        $this->getJson('/api/cajas/al-ingresar')->assertJson(['abrir' => false]);
    }

    public function test_sin_el_permiso_la_ruta_no_responde(): void
    {
        $this->actuarCon('cajas.abrir');

        $this->getJson('/api/cajas/al-ingresar')->assertForbidden();
    }
}
