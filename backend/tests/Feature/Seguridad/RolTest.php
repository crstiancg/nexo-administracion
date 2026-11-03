<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class RolTest extends TestCase
{
    use ActuaConPermisos, RefreshDatabase;

    private function actuarConPermiso(): void
    {
        $this->actuarCon('roles.index', 'roles.show', 'roles.store', 'roles.update', 'roles.destroy');
    }

    public function test_sin_permisos_devuelve_403(): void
    {
        $this->actuarCon();

        $this->getJson('/api/roles')->assertForbidden();
    }

    public function test_index_trae_los_roles_con_sus_permisos(): void
    {
        $this->actuarConPermiso();
        Role::create(['name' => 'Cajero'])->givePermissionTo(Permission::create(['name' => 'ver-ventas', 'description' => 'Ver ventas']));

        $this->getJson('/api/roles?rowsPerPage=10&search=Caj')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Cajero')
            ->assertJsonPath('data.0.permissions.0.name', 'ver-ventas');
    }

    public function test_store_crea_el_rol_y_sincroniza_permisos(): void
    {
        $this->actuarConPermiso();
        $permiso = Permission::create(['name' => 'ver-ventas', 'description' => 'Ver ventas']);

        $this->postJson('/api/roles', ['rol' => ['name' => 'Cajero', 'permisosSelected' => [$permiso->id]]])
            ->assertCreated()
            ->assertJsonPath('name', 'Cajero');

        $this->assertTrue(Role::findByName('Cajero', 'api')->hasPermissionTo('ver-ventas'));
    }

    public function test_store_valida_nombre_requerido_y_unico(): void
    {
        $this->actuarConPermiso();
        Role::create(['name' => 'Cajero']);

        $this->postJson('/api/roles', ['rol' => ['name' => 'Cajero']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rol.name']);

        $this->postJson('/api/roles', ['rol' => ['name' => '']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rol.name']);
    }

    public function test_store_rechaza_permisos_inexistentes(): void
    {
        $this->actuarConPermiso();

        $this->postJson('/api/roles', ['rol' => ['name' => 'Cajero', 'permisosSelected' => [999]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rol.permisosSelected.0']);
    }

    public function test_show_devuelve_el_rol_y_los_ids_de_sus_permisos(): void
    {
        $this->actuarConPermiso();
        $permiso = Permission::create(['name' => 'ver-ventas', 'description' => 'Ver ventas']);
        $rol = Role::create(['name' => 'Cajero'])->givePermissionTo($permiso);

        $this->getJson("/api/roles/{$rol->id}")
            ->assertOk()
            ->assertJsonPath('rol.name', 'Cajero')
            ->assertJsonPath('permisosSelected', [$permiso->id]);
    }

    public function test_update_conserva_el_nombre_y_resincroniza_permisos(): void
    {
        $this->actuarConPermiso();
        $viejo = Permission::create(['name' => 'ver-ventas', 'description' => 'Ver ventas']);
        $nuevo = Permission::create(['name' => 'ver-reportes', 'description' => 'Ver reportes']);
        $rol = Role::create(['name' => 'Cajero'])->givePermissionTo($viejo);

        $this->putJson("/api/roles/{$rol->id}", ['rol' => ['name' => 'Cajero', 'permisosSelected' => [$nuevo->id]]])
            ->assertOk();

        $rol->refresh();
        $this->assertTrue($rol->hasPermissionTo('ver-reportes'));
        $this->assertFalse($rol->hasPermissionTo('ver-ventas'));
    }

    public function test_update_sin_permisos_los_quita_todos(): void
    {
        $this->actuarConPermiso();
        $rol = Role::create(['name' => 'Cajero'])->givePermissionTo(Permission::create(['name' => 'ver-ventas', 'description' => 'x']));

        $this->putJson("/api/roles/{$rol->id}", ['rol' => ['name' => 'Cajero', 'permisosSelected' => []]])->assertOk();

        $this->assertCount(0, $rol->fresh()->permissions);
    }

    public function test_destroy(): void
    {
        $this->actuarConPermiso();
        $rol = Role::create(['name' => 'Cajero']);

        $this->deleteJson("/api/roles/{$rol->id}")->assertNoContent();

        $this->assertDatabaseMissing('roles', ['id' => $rol->id]);
    }
}
