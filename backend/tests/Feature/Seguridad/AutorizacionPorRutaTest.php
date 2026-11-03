<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * Modelo "tipo Casbin": el permiso ES el nombre de la ruta y todo lo que no
 * está permitido explícitamente se rechaza.
 */
class AutorizacionPorRutaTest extends TestCase
{
    use ActuaConPermisos, RefreshDatabase;

    public function test_sin_permisos_toda_ruta_protegida_da_403(): void
    {
        $this->actuarCon();

        $this->getJson('/api/roles')->assertForbidden();
        $this->getJson('/api/permisos')->assertForbidden();
        $this->getJson('/api/usuarios')->assertForbidden();
    }

    public function test_el_permiso_es_por_accion_no_por_modulo(): void
    {
        $this->actuarCon('roles.index');

        $this->getJson('/api/roles')->assertOk();
        $this->postJson('/api/roles', ['rol' => ['name' => 'Cajero']])->assertForbidden();
        $this->deleteJson('/api/roles/1')->assertForbidden();
    }

    public function test_sin_permiso_no_se_revela_si_el_registro_existe(): void
    {
        $this->actuarCon();
        $rol = Role::create(['name' => 'Cajero']);

        $this->getJson("/api/roles/{$rol->id}")->assertForbidden();
        $this->getJson('/api/roles/99999')->assertForbidden();
    }

    public function test_los_permisos_heredados_por_rol_tambien_cuentan(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'Lector'])->givePermissionTo(Permission::findOrCreate('roles.index', 'api')));
        Passport::actingAs($user);

        $this->getJson('/api/roles')->assertOk();
    }

    public function test_las_rutas_libres_solo_exigen_sesion(): void
    {
        $this->actuarCon();

        $this->getJson('/api/user')->assertOk();
    }

    public function test_una_ruta_protegida_sin_nombre_se_rechaza(): void
    {
        // Sin nombre no hay permiso que la habilite: se bloquea en vez de
        // quedar abierta por olvido.
        Route::middleware(['api', 'auth:api', 'autorizar.ruta'])->get('/api/sin-nombre', fn () => 'ok');
        $this->actuarCon('roles.index');

        $this->getJson('/api/sin-nombre')->assertForbidden();
    }

    public function test_implicitos_el_form_de_usuarios_puede_leer_roles_y_permisos(): void
    {
        $this->actuarCon('usuarios.update');

        $this->getJson('/api/roles?rowsPerPage=0')->assertOk();
        $this->getJson('/api/permisos?rowsPerPage=0')->assertOk();
        // Leer el catálogo no habilita modificarlo.
        $this->postJson('/api/roles', ['rol' => ['name' => 'X']])->assertForbidden();
    }

    public function test_implicitos_quien_edita_puede_leer_el_registro(): void
    {
        $this->actuarCon('roles.update');
        $rol = Role::create(['name' => 'Cajero']);

        $this->getJson("/api/roles/{$rol->id}")->assertOk();
    }

    public function test_la_validacion_precognitiva_exige_el_mismo_permiso_que_guardar(): void
    {
        $this->actuarCon('roles.index');

        $this->withHeaders(['Precognition' => 'true'])
            ->postJson('/api/roles', ['rol' => ['name' => 'Cajero']])
            ->assertForbidden();
    }
}
