<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermisoRequest;
use App\Http\Requests\UpdatePermisoRequest;
use App\Support\RutasProtegidas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cada permiso es una ruta protegida. Se crean eligiendo entre las rutas que
 * todavía no tienen el suyo (acá o con `php artisan permisos:sync`); no se
 * borran por API: un permiso huérfano lo limpia `permisos:sync --prune`.
 */
class PermisoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->generateViewSetList(
            $request,
            Permission::query(),
            [],
            ['name', 'description'],
            ['id', 'name', 'description'],
        );
    }

    /**
     * Rutas protegidas sin permiso, agrupadas por recurso: lo que ofrece el
     * diálogo "Nuevo permiso" (un recurso entero o rutas sueltas).
     */
    public function rutasDisponibles(RutasProtegidas $rutas): JsonResponse
    {
        return response()->json($rutas->agrupadas($rutas->disponibles()));
    }

    public function store(StorePermisoRequest $request, RutasProtegidas $rutas): JsonResponse
    {
        $creados = DB::transaction(fn () => collect($request->validated('permiso.rutas'))
            ->map(fn (string $nombre) => Permission::create([
                'name' => $nombre,
                'guard_name' => 'api',
                'description' => $rutas->describir($nombre),
            ])));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json($creados->values(), 201);
    }

    public function show(Permission $permiso): JsonResponse
    {
        return response()->json($permiso);
    }

    public function update(UpdatePermisoRequest $request, Permission $permiso): JsonResponse
    {
        $permiso->update(['description' => $request->validated('permiso.description')]);

        return response()->json($permiso);
    }
}
