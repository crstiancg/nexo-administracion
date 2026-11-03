<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRolRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->generateViewSetList(
            $request,
            // Los permisos vienen con el rol para mostrar en la lista cuántos
            // tiene y, más adelante, qué hereda un usuario de cada rol.
            Role::query()->with('permissions:id,name,description'),
            [],
            ['name'],
            ['id', 'name'],
        );
    }

    public function store(StoreRolRequest $request): JsonResponse
    {
        $rol = DB::transaction(function () use ($request) {
            $rol = Role::create(['name' => $request->validated('rol.name'), 'guard_name' => 'api']);
            $rol->syncPermissions($request->validated('rol.permisosSelected', []));

            return $rol;
        });

        return response()->json($rol, 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'rol' => $role->only('id', 'name'),
            'permisosSelected' => $role->permissions->pluck('id'),
        ]);
    }

    public function update(StoreRolRequest $request, Role $role): JsonResponse
    {
        DB::transaction(function () use ($request, $role) {
            $role->update(['name' => $request->validated('rol.name')]);
            $role->syncPermissions($request->validated('rol.permisosSelected', []));
        });

        return response()->json($role);
    }

    public function destroy(Role $role): Response
    {
        $role->delete();

        return response()->noContent();
    }
}
