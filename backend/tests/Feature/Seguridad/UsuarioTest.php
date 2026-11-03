<?php

namespace Tests\Feature\Seguridad;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    private User $yo;

    private function actuarConPermiso(): void
    {
        $this->yo = User::factory()->create(['name' => 'Yo Admin', 'username' => 'yo']);
        foreach (['index', 'show', 'store', 'update', 'destroy', 'toggle-active', 'sesiones', 'sesiones.revocar'] as $accion) {
            $this->yo->givePermissionTo(Permission::findOrCreate("usuarios.{$accion}", 'api'));
        }
        Passport::actingAs($this->yo);
    }

    private function payload(array $overrides = []): array
    {
        return ['usuario' => array_merge([
            'name' => 'Ana Pérez',
            'username' => 'aperez',
            'email' => 'ana@forkids.test',
            'password' => 'secreto123',
            'rolesSelected' => [],
            'permisosSelected' => [],
        ], $overrides)];
    }

    public function test_sin_permisos_devuelve_403(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/usuarios')->assertForbidden();
    }

    public function test_index_busca_por_nombre_usuario_o_email_y_trae_roles(): void
    {
        $this->actuarConPermiso();
        User::factory()->create(['name' => 'Ana Pérez', 'username' => 'aperez'])->assignRole(Role::create(['name' => 'Cajero']));
        User::factory()->create(['name' => 'Beto Gómez', 'username' => 'bgomez']);

        $this->getJson('/api/usuarios?rowsPerPage=10&search=aper')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.username', 'aperez')
            ->assertJsonPath('data.0.roles.0.name', 'Cajero')
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_store_crea_con_roles_y_permisos_y_hashea_la_contrasena(): void
    {
        $this->actuarConPermiso();
        $rol = Role::create(['name' => 'Cajero']);
        $permiso = Permission::create(['name' => 'ver-ventas', 'description' => 'Ver ventas']);

        $this->postJson('/api/usuarios', $this->payload(['rolesSelected' => [$rol->id], 'permisosSelected' => [$permiso->id]]))
            ->assertCreated()
            ->assertJsonPath('username', 'aperez');

        $user = User::where('username', 'aperez')->first();
        $this->assertTrue(Hash::check('secreto123', $user->password));
        $this->assertTrue($user->hasRole('Cajero'));
        $this->assertTrue($user->hasDirectPermission('ver-ventas'));
        $this->assertTrue($user->active);
    }

    public function test_store_valida_campos(): void
    {
        $this->actuarConPermiso();

        $this->postJson('/api/usuarios', $this->payload([
            'name' => '', 'username' => 'yo', 'email' => 'no-es-email', 'password' => 'corta', 'rolesSelected' => [999],
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'usuario.name', 'usuario.username', 'usuario.email', 'usuario.password', 'usuario.rolesSelected.0',
        ]);
    }

    public function test_store_exige_contrasena_y_permite_email_vacio(): void
    {
        $this->actuarConPermiso();

        $this->postJson('/api/usuarios', $this->payload(['password' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usuario.password']);

        $this->postJson('/api/usuarios', $this->payload(['email' => null]))->assertCreated();
    }

    public function test_show_devuelve_usuario_y_ids_seleccionados(): void
    {
        $this->actuarConPermiso();
        $rol = Role::create(['name' => 'Cajero']);
        $permiso = Permission::create(['name' => 'ver-ventas', 'description' => 'x']);
        $user = User::factory()->create()->assignRole($rol)->givePermissionTo($permiso);

        $this->getJson("/api/usuarios/{$user->id}")
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('rolesSelected', [$rol->id])
            ->assertJsonPath('permisosSelected', [$permiso->id])
            ->assertJsonMissingPath('user.password');
    }

    public function test_update_sin_contrasena_conserva_la_actual_y_permite_mismo_username(): void
    {
        $this->actuarConPermiso();
        $user = User::factory()->create(['username' => 'aperez', 'email' => 'ana@forkids.test', 'password' => 'original123']);

        $this->putJson("/api/usuarios/{$user->id}", $this->payload(['name' => 'Ana María', 'password' => '']))
            ->assertOk();

        $user->refresh();
        $this->assertSame('Ana María', $user->name);
        $this->assertTrue(Hash::check('original123', $user->password));
    }

    public function test_update_con_contrasena_la_cambia_y_resincroniza_roles(): void
    {
        $this->actuarConPermiso();
        $viejo = Role::create(['name' => 'Cajero']);
        $nuevo = Role::create(['name' => 'Supervisor']);
        $user = User::factory()->create(['username' => 'aperez'])->assignRole($viejo);

        $this->putJson("/api/usuarios/{$user->id}", $this->payload(['password' => 'nueva12345', 'rolesSelected' => [$nuevo->id]]))
            ->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('nueva12345', $user->password));
        $this->assertSame(['Supervisor'], $user->getRoleNames()->all());
    }

    public function test_toggle_active_da_de_baja_y_revoca_sus_tokens(): void
    {
        $this->actuarConPermiso();
        app(ClientRepository::class)->createPersonalAccessGrantClient('test', 'users');
        $user = User::factory()->create();
        $user->createToken('sesion');

        $this->patchJson("/api/usuarios/{$user->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('active', false);

        $this->assertFalse($user->fresh()->active);
        $this->assertTrue($user->tokens()->first()->revoked);

        $this->patchJson("/api/usuarios/{$user->id}/toggle-active")->assertJsonPath('active', true);
    }

    public function test_no_te_podes_dar_de_baja_ni_borrar_a_vos_mismo(): void
    {
        $this->actuarConPermiso();

        $this->patchJson("/api/usuarios/{$this->yo->id}/toggle-active")->assertUnprocessable();
        $this->deleteJson("/api/usuarios/{$this->yo->id}")->assertUnprocessable();

        $this->assertTrue($this->yo->fresh()->active);
    }

    public function test_destroy(): void
    {
        $this->actuarConPermiso();
        $user = User::factory()->create();

        $this->deleteJson("/api/usuarios/{$user->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_sesiones_lista_las_activas_y_revoca_solo_las_del_usuario(): void
    {
        $this->actuarConPermiso();
        app(ClientRepository::class)->createPersonalAccessGrantClient('test', 'users');
        $user = User::factory()->create();
        $otro = User::factory()->create();
        $token = $user->createToken('sesion')->getToken();
        $ajeno = $otro->createToken('sesion')->getToken();

        $this->getJson("/api/usuarios/{$user->id}/sesiones")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $token->id);

        // Un token de OTRO usuario no se puede revocar por la URL de éste.
        $this->deleteJson("/api/usuarios/{$user->id}/sesiones/{$ajeno->id}")->assertNotFound();
        $this->assertFalse($ajeno->fresh()->revoked);

        $this->deleteJson("/api/usuarios/{$user->id}/sesiones/{$token->id}")->assertNoContent();
        $this->assertTrue($token->fresh()->revoked);
    }
}
