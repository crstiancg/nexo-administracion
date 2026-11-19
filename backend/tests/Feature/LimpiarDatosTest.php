<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Sede;
use App\Models\Sorteo;
use App\Models\TipoEquipo;
use App\Models\User;
use App\Support\Fechas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LimpiarDatosTest extends TestCase
{
    use RefreshDatabase;

    public function test_borra_la_operacion_y_conserva_usuarios_roles_configuracion_y_catalogos(): void
    {
        Storage::fake('public');
        $sede = Sede::create(['nombre' => 'Matriz']);
        $usuario = User::factory()->create(['sede_id' => $sede->id]);
        $usuario->assignRole(Role::findOrCreate('Administrador', 'api'));
        Configuracion::query()->create(['clave' => 'empresa', 'valor' => ['nombre' => 'NEXO']]);
        Categoria::create(['nombre' => 'Laptops']);
        TipoEquipo::create(['nombre' => 'Laptop', 'checklist' => [], 'activo' => true]);

        Cliente::create(['nombre' => 'Ana Quispe']);
        Sorteo::query()->forceCreate([
            'nombre' => 'Sorteo', 'periodo' => 'mensual', 'estado' => 'abierto',
            'inicia_el' => Fechas::hoy()->toDateString(), 'termina_el' => Fechas::hoy()->toDateString(),
        ]);
        Storage::disk('public')->put('ordenes/1/foto.jpg', 'x');

        $this->artisan('nexo:limpiar-datos', ['--force' => true])->assertSuccessful();

        // Se va la operación…
        $this->assertSame(0, Cliente::count());
        $this->assertSame(0, Sorteo::count());
        Storage::disk('public')->assertMissing('ordenes/1/foto.jpg');
        // …y queda lo de base.
        $this->assertSame(1, User::count());
        $this->assertTrue($usuario->fresh()->hasRole('Administrador'));
        $this->assertSame($sede->id, $usuario->fresh()->sede_id);
        $this->assertSame(1, Configuracion::count());
        $this->assertSame(1, Categoria::count());
        $this->assertSame(1, TipoEquipo::count());
    }

    public function test_simular_no_borra_nada(): void
    {
        Cliente::create(['nombre' => 'Ana Quispe']);

        $this->artisan('nexo:limpiar-datos', ['--simular' => true])->assertSuccessful();

        $this->assertSame(1, Cliente::count());
    }
}
