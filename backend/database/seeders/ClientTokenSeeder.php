<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;

/**
 * Cliente del password grant que usa el frontend, con ID y secret fijos (igual
 * que en muni-asis-sitra) para que el front los conozca de antemano. Se pueden
 * pisar con PASSPORT_PASSWORD_CLIENT_ID / _SECRET en el .env.
 *
 * Passport 13 guarda el secret hasheado, por eso se crea con el modelo y no con
 * DB::table como en Passport 12.
 */
class ClientTokenSeeder extends Seeder
{
    public const DEFAULT_CLIENT_ID = 'ebacc5c8-57de-47a5-895a-08daa99ed8de';

    public const DEFAULT_CLIENT_SECRET = '210e44d745969f390b25ce75a675bfd555294c35';

    public function run(): void
    {
        $id = config('passport.password_client.id') ?: self::DEFAULT_CLIENT_ID;
        $secret = config('passport.password_client.secret') ?: self::DEFAULT_CLIENT_SECRET;

        if (Client::query()->whereKey($id)->exists()) {
            return;
        }

        Client::forceCreate([
            'id' => $id,
            'name' => 'Laravel Password Grant Client',
            'secret' => $secret,
            'provider' => 'users',
            'redirect_uris' => [],
            'grant_types' => ['password', 'refresh_token'],
            'revoked' => false,
        ]);
    }
}
