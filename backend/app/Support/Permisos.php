<?php

namespace App\Support;

use App\Models\User;

/**
 * La misma regla que el middleware `autorizar.ruta`, para preguntarla desde
 * adentro: ¿este usuario podría entrar a la ruta X? (su permiso o cualquiera
 * de los que la habilitan en `permisos.implicitos`). La usan las pantallas
 * que juntan datos de varios módulos (dashboard, alertas, búsqueda) para
 * mostrar a cada uno sólo lo suyo.
 */
class Permisos
{
    public static function puede(?User $user, string $ruta): bool
    {
        if ($user === null) {
            return false;
        }

        // Ojo: la clave lleva puntos (roles.index), así que NO se puede leer
        // con config("permisos.implicitos.$ruta").
        $aceptados = [$ruta, ...(config('permisos.implicitos', [])[$ruta] ?? [])];

        foreach ($aceptados as $permiso) {
            // checkPermissionTo y no hasPermissionTo: si el permiso no existe
            // en la base (ruta nueva sin sincronizar) da false, no una excepción.
            if ($user->checkPermissionTo($permiso, 'api')) {
                return true;
            }
        }

        return false;
    }
}
