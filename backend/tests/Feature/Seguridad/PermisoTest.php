<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * Los permisos salen de las rutas (permisos:sync): desde la API sólo se
 * listan, se ven y se les edita la descripción.
 */
class PermisoTest extends TestCase
{
    use ActuaConPermisos, RefreshDatabase;

    public function test_sin_permisos_index_devuelve_403(): void
    {
        $this->actuarCon();

        $this->getJson('/api/permisos')->assertForbidden();
    }

    public function test_index_pagina_busca_y_ordena(): void
    {
        $this->actuarCon('permisos.index');
        Permission::create(['name' => 'ventas.index', 'guard_name' => 'api', 'description' => 'Ventas · Ver']);
        Permission::create(['name' => 'ventas.store', 'guard_name' => 'api', 'description' => 'Ventas · Crear']);

        $this->getJson('/api/permisos?rowsPerPage=1&page=1&search=ventas.&order_by=-name')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('data.0.name', 'ventas.store');
    }

    public function test_no_se_pueden_borrar_por_api(): void
    {
        $this->actuarCon('permisos.index', 'permisos.update');
        $permiso = Permission::create(['name' => 'ventas.index', 'guard_name' => 'api']);

        $this->deleteJson("/api/permisos/{$permiso->id}")->assertMethodNotAllowed();
    }

    public function test_rutas_disponibles_lista_solo_las_protegidas_sin_permiso_agrupadas_por_recurso(): void
    {
        // Todo sincronizado menos roles.store y roles.destroy.
        $this->artisan('permisos:sync');
        Permission::whereIn('name', ['roles.store', 'roles.destroy'])->delete();
        $this->actuarCon('permisos.store');

        $response = $this->getJson('/api/permisos/rutas-disponibles')->assertOk();

        $response->assertJsonCount(1)
            ->assertJsonPath('0.recurso', 'roles')
            ->assertJsonPath('0.nombre', 'Roles')
            ->assertJsonPath('0.rutas.0.name', 'roles.destroy')
            ->assertJsonPath('0.rutas.0.description', 'Roles · Eliminar')
            ->assertJsonPath('0.rutas.0.metodo', 'DELETE')
            ->assertJsonPath('0.rutas.0.uri', 'api/roles/{role}')
            ->assertJsonPath('0.rutas.1.name', 'roles.store');
    }

    public function test_store_crea_los_permisos_de_las_rutas_elegidas(): void
    {
        $this->artisan('permisos:sync');
        Permission::whereIn('name', ['roles.store', 'roles.destroy'])->delete();
        $this->actuarCon('permisos.store');

        $this->postJson('/api/permisos', ['permiso' => ['rutas' => ['roles.store', 'roles.destroy']]])
            ->assertCreated()
            ->assertJsonCount(2);

        $this->assertSame('Roles · Crear', Permission::findByName('roles.store', 'api')->description);
        $this->assertDatabaseHas('permissions', ['name' => 'roles.destroy', 'guard_name' => 'api']);
    }

    public function test_store_solo_acepta_rutas_disponibles(): void
    {
        $this->artisan('permisos:sync');
        $this->actuarCon('permisos.store');

        // Inventada, libre y ya existente: ninguna es una ruta disponible.
        $this->postJson('/api/permisos', ['permiso' => ['rutas' => ['roles.stroe', 'auth.user', 'roles.index']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permiso.rutas.0', 'permiso.rutas.1', 'permiso.rutas.2']);

        $this->postJson('/api/permisos', ['permiso' => ['rutas' => []]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permiso.rutas']);
    }

    public function test_crear_exige_permisos_store(): void
    {
        $this->actuarCon('permisos.index');

        $this->getJson('/api/permisos/rutas-disponibles')->assertForbidden();
        $this->postJson('/api/permisos', ['permiso' => ['rutas' => ['roles.store']]])->assertForbidden();
    }

    public function test_update_cambia_la_descripcion_y_nunca_el_nombre(): void
    {
        $this->actuarCon('permisos.update');
        $permiso = Permission::create(['name' => 'ventas.index', 'guard_name' => 'api', 'description' => 'Viejo']);

        $this->putJson("/api/permisos/{$permiso->id}", ['permiso' => ['name' => 'hackeado', 'description' => 'Ventas · Ver listado']])
            ->assertOk();

        $permiso->refresh();
        $this->assertSame('ventas.index', $permiso->name);
        $this->assertSame('Ventas · Ver listado', $permiso->description);
    }

    public function test_update_exige_descripcion(): void
    {
        $this->actuarCon('permisos.update');
        $permiso = Permission::create(['name' => 'ventas.index', 'guard_name' => 'api']);

        $this->putJson("/api/permisos/{$permiso->id}", ['permiso' => ['description' => '']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permiso.description']);
    }

    public function test_show(): void
    {
        $this->actuarCon('permisos.show');
        $permiso = Permission::create(['name' => 'ventas.index', 'guard_name' => 'api']);

        $this->getJson("/api/permisos/{$permiso->id}")->assertOk()->assertJsonPath('name', 'ventas.index');
    }
}
