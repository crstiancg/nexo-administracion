<?php

namespace App\Http\Middleware;

use App\Support\Permisos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorización "tipo Casbin" sobre spatie: el permiso requerido es el nombre
 * de la ruta. Rechaza por defecto: una ruta sin nombre, o con un nombre que
 * nadie tiene asignado, devuelve 403 en vez de quedar abierta por olvido.
 */
class AutorizarPorRuta
{
    public const ALIAS = 'autorizar.ruta';

    public function handle(Request $request, Closure $next): Response
    {
        $nombre = $request->route()?->getName();

        if ($nombre !== null && in_array($nombre, config('permisos.libres', []), true)) {
            return $next($request);
        }

        $user = $request->user();

        if ($nombre === null || $user === null) {
            abort(403, 'No tenés permiso para realizar esta acción.');
        }

        if (Permisos::puede($user, $nombre)) {
            return $next($request);
        }

        abort(403, 'No tenés permiso para realizar esta acción.');
    }
}
