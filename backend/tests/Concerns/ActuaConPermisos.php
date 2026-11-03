<?php

namespace Tests\Concerns;

use App\Models\User;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

trait ActuaConPermisos
{
    /**
     * Loguea (Passport) un usuario nuevo con exactamente estos permisos
     * directos. Los permisos son nombres de ruta: `roles.index`, etc.
     */
    protected function actuarCon(string ...$permisos): User
    {
        $user = User::factory()->create();

        foreach ($permisos as $permiso) {
            $user->givePermissionTo(Permission::findOrCreate($permiso, 'api'));
        }

        Passport::actingAs($user);

        return $user;
    }
}
