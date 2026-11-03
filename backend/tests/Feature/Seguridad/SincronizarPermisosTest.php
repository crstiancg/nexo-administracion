<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SincronizarPermisosTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_permiso_por_cada_ruta_protegida_con_descripcion_legible(): void
    {
        $this->artisan('permisos:sync')->assertSuccessful();

        foreach (['roles.index', 'roles.store', 'roles.update', 'roles.destroy', 'usuarios.toggle-active', 'usuarios.sesiones'] as $nombre) {
            $this->assertDatabaseHas('permissions', ['name' => $nombre, 'guard_name' => 'api']);
        }

        $this->assertSame('Roles · Crear', Permission::findByName('roles.store', 'api')->description);
        $this->assertSame('Usuarios · Dar de baja / activar', Permission::findByName('usuarios.toggle-active', 'api')->description);
    }

    public function test_no_crea_permisos_para_las_rutas_libres(): void
    {
        $this->artisan('permisos:sync');

        $this->assertDatabaseMissing('permissions', ['name' => 'auth.user']);
        $this->assertDatabaseMissing('permissions', ['name' => 'auth.logout']);
    }

    public function test_no_pisa_la_descripcion_que_edito_un_administrador(): void
    {
        Permission::create(['name' => 'roles.store', 'guard_name' => 'api', 'description' => 'Alta de roles']);

        $this->artisan('permisos:sync');

        $this->assertSame('Alta de roles', Permission::findByName('roles.store', 'api')->description);
    }

    public function test_prune_borra_los_que_ya_no_son_rutas(): void
    {
        Permission::create(['name' => 'admin-roles', 'guard_name' => 'api']);

        $this->artisan('permisos:sync')->assertSuccessful();
        $this->assertDatabaseHas('permissions', ['name' => 'admin-roles']);

        $this->artisan('permisos:sync --prune')->assertSuccessful();
        $this->assertDatabaseMissing('permissions', ['name' => 'admin-roles']);
        $this->assertDatabaseHas('permissions', ['name' => 'roles.index']);
    }

    public function test_es_idempotente(): void
    {
        $this->artisan('permisos:sync');
        $total = Permission::count();

        $this->artisan('permisos:sync');

        $this->assertSame($total, Permission::count());
    }
}
