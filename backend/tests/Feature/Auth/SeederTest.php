<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\ClientTokenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que deja la siembra fuera de desarrollo (APP_ENV=testing): el cliente
 * de Passport y el admin con la clave de ADMIN_PASSWORD (o "nexo" si no se
 * definió). El usuario de prueba y los datos de demostración son sólo de
 * `local`.
 */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE_ADMIN = 'clave-de-prueba';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'passport.password_client.id' => '9d1f6a3e-0000-4000-8000-000000000001',
            'passport.password_client.secret' => 'secret-de-prueba',
        ]);
        // El seeder la lee con env(): una fija para que no dependa del .env de cada uno.
        $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = self::CLAVE_ADMIN;
    }

    protected function tearDown(): void
    {
        unset($_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);

        parent::tearDown();
    }

    private function login(string $clientId, string $secret, string $username, string $password): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $clientId,
            'client_secret' => $secret,
            'username' => $username,
            'password' => $password,
            'scope' => '',
        ]);
    }

    public function test_el_seeder_deja_listo_el_login_del_admin_con_el_cliente_configurado(): void
    {
        $this->seed();

        $this->login('9d1f6a3e-0000-4000-8000-000000000001', 'secret-de-prueba', 'admin', self::CLAVE_ADMIN)
            ->assertOk()
            ->assertJsonStructure(['access_token']);
    }

    public function test_fuera_de_desarrollo_no_crea_el_usuario_de_prueba(): void
    {
        $this->seed();

        $this->assertDatabaseMissing('users', ['email' => 'password@gmail.com']);
        $this->login('9d1f6a3e-0000-4000-8000-000000000001', 'secret-de-prueba', 'password@gmail.com', 'password')
            ->assertStatus(400);
    }

    public function test_el_seeder_da_rol_administrador_con_todos_los_permisos_de_rutas(): void
    {
        $this->seed();

        $admin = User::where('username', 'admin')->firstOrFail();
        $this->assertTrue($admin->hasRole('Administrador'));
        // El Administrador recibe todos los permisos de rutas.
        foreach (['roles.index', 'roles.store', 'permisos.update', 'usuarios.toggle-active'] as $permiso) {
            $this->assertTrue($admin->hasPermissionTo($permiso), $permiso);
        }
    }

    public function test_el_seeder_es_idempotente(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('oauth_clients', 1);
    }

    public function test_sin_nada_en_el_env_siembra_el_cliente_por_defecto_y_la_clave_por_defecto(): void
    {
        config([
            'passport.password_client.id' => null,
            'passport.password_client.secret' => null,
        ]);
        unset($_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);

        $this->seed();

        $this->login(ClientTokenSeeder::DEFAULT_CLIENT_ID, ClientTokenSeeder::DEFAULT_CLIENT_SECRET, 'admin', 'nexo')
            ->assertOk();
    }
}
