<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_exige_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_user_devuelve_el_usuario_autenticado_sin_password(): void
    {
        $user = User::factory()->create(['name' => 'Cristian G.', 'username' => 'admin']);
        Passport::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.username', 'admin')
            ->assertJsonPath('user.name', 'Cristian G.')
            ->assertJsonMissingPath('user.password');
    }

    public function test_user_incluye_roles_y_permisos_directos_y_heredados(): void
    {
        $user = User::factory()->create();
        $rol = Role::create(['name' => 'Cajero']);
        $rol->givePermissionTo(Permission::create(['name' => 'ver-ventas']));
        $user->assignRole($rol);
        $user->givePermissionTo(Permission::create(['name' => 'ver-reportes']));
        Passport::actingAs($user);

        $response = $this->getJson('/api/user')->assertOk()->assertJsonPath('roles', ['Cajero']);

        $this->assertEqualsCanonicalizing(['ver-ventas', 'ver-reportes'], $response->json('permisos'));
        $response->assertJsonMissingPath('user.roles');
    }

    public function test_logout_revoca_el_token_actual(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('test', 'users');
        $user = User::factory()->create();
        $token = $user->createToken('test')->accessToken;
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/api/logout', [], $headers)->assertNoContent();

        $this->assertTrue($user->tokens()->first()->revoked);
    }
}
