<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_SECRET = 'secret-de-prueba';

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::forceCreate([
            'name' => 'Cliente de prueba',
            'secret' => self::CLIENT_SECRET,
            'provider' => 'users',
            'redirect_uris' => [],
            'grant_types' => ['password', 'refresh_token'],
            'revoked' => false,
        ]);
    }

    private function requestToken(string $username, string $password): TestResponse
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $this->client->id,
            'client_secret' => self::CLIENT_SECRET,
            'username' => $username,
            'password' => $password,
            'scope' => '',
        ]);
    }

    public function test_emite_token_con_username_y_password_correctos(): void
    {
        User::factory()->create(['username' => 'admin', 'password' => 'forkids']);

        $this->requestToken('admin', 'forkids')
            ->assertOk()
            ->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token']);
    }

    public function test_el_username_no_distingue_mayusculas_ni_espacios(): void
    {
        User::factory()->create(['username' => 'admin', 'password' => 'forkids']);

        $this->requestToken('  ADMIN ', 'forkids')->assertOk();
    }

    public function test_tambien_acepta_el_email_como_usuario(): void
    {
        User::factory()->create([
            'username' => 'password',
            'email' => 'password@gmail.com',
            'password' => 'password',
        ]);

        $this->requestToken('Password@Gmail.com', 'password')->assertOk();
    }

    public function test_rechaza_password_incorrecto(): void
    {
        User::factory()->create(['username' => 'admin', 'password' => 'forkids']);

        $this->requestToken('admin', 'otra-cosa')
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_rechaza_usuario_inexistente_con_el_mismo_error(): void
    {
        $this->requestToken('nadie', 'forkids')
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_rechaza_usuario_inactivo(): void
    {
        User::factory()->create(['username' => 'admin', 'password' => 'forkids', 'active' => false]);

        $this->requestToken('admin', 'forkids')
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }
}
