<?php

use App\Http\Middleware\AutorizarPorRuta;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás del proxy de Dokploy (Traefik): sin esto las URLs que arma
        // Laravel (fotos, paginación) saldrían en http.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            AutorizarPorRuta::ALIAS => AutorizarPorRuta::class,
        ]);

        // Autorizar ANTES de buscar el modelo de la ruta: si no, sin permiso
        // un ID inexistente da 404 y uno existente 403, y eso revela qué
        // registros hay. Queda igual después de auth:api (necesita al usuario).
        $middleware->prependToPriorityList(SubstituteBindings::class, AutorizarPorRuta::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
