<?php

namespace App\Support;

use App\Http\Middleware\AutorizarPorRuta;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Las rutas que exigen permiso (las que pasan por `autorizar.ruta`). Es la
 * única fuente de nombres de permisos: la usan `permisos:sync` y la pantalla
 * de "Nuevo permiso", así ninguno de los dos tipea nombres a mano.
 */
class RutasProtegidas
{
    public function __construct(private readonly Router $router) {}

    /**
     * @return Collection<string, Route> nombre => ruta, ordenadas por nombre
     */
    public function todas(): Collection
    {
        $libres = config('permisos.libres', []);

        return collect($this->router->getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array(AutorizarPorRuta::ALIAS, $route->gatherMiddleware(), true))
            ->filter(fn (Route $route) => $route->getName() !== null)
            ->reject(fn (Route $route) => in_array($route->getName(), $libres, true))
            ->keyBy(fn (Route $route) => $route->getName())
            ->sortKeys();
    }

    /**
     * Las protegidas que todavía no tienen su permiso creado.
     *
     * @return Collection<string, Route>
     */
    public function disponibles(): Collection
    {
        $existentes = Permission::where('guard_name', 'api')->pluck('name')->all();

        return $this->todas()->reject(fn (Route $route, string $nombre) => in_array($nombre, $existentes, true));
    }

    /**
     * Agrupa por recurso (el prefijo del nombre) para mostrarlas en la UI.
     *
     * @param  Collection<string, Route>  $rutas
     * @return list<array{recurso: string, nombre: string, rutas: list<array{name: string, description: string, metodo: string, uri: string}>}>
     */
    public function agrupadas(Collection $rutas): array
    {
        return $rutas
            ->groupBy(fn (Route $route, string $nombre) => explode('.', $nombre)[0], preserveKeys: true)
            ->sortKeys()
            ->map(fn (Collection $grupo, string $recurso) => [
                'recurso' => $recurso,
                'nombre' => config('permisos.recursos')[$recurso] ?? ucfirst($recurso),
                'rutas' => $grupo->map(fn (Route $route, string $nombre) => [
                    'name' => $nombre,
                    'description' => $this->describir($nombre),
                    'metodo' => $this->metodo($route),
                    'uri' => $route->uri(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * roles.store → "Roles · Crear"; usuarios.sesiones.revocar → "Usuarios · Cerrar sesiones".
     */
    public function describir(string $nombre): string
    {
        [$recurso, $accion] = array_pad(explode('.', $nombre, 2), 2, '');

        $recursoLabel = config('permisos.recursos')[$recurso] ?? ucfirst($recurso);
        $accionLabel = config('permisos.acciones')[$accion] ?? $accion;

        return trim("{$recursoLabel} · {$accionLabel}", ' ·');
    }

    /**
     * El verbo que importa para leerla: sin HEAD (va con GET) y PUT en vez de
     * "PUT|PATCH" (apiResource registra los dos para update).
     */
    private function metodo(Route $route): string
    {
        return collect($route->methods())->reject(fn (string $m) => $m === 'HEAD')->first() ?? 'GET';
    }
}
