<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Token;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->generateViewSetList(
            $request,
            User::query()->visiblesPara($request->user())->with(['roles:id,name', 'sede:id,nombre']),
            [],
            ['name', 'username', 'email'],
            ['id', 'name', 'username', 'email', 'active'],
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($this->datos($request));
            $this->sincronizarAccesos($user, $request);

            return $user;
        });

        return response()->json($user, 201);
    }

    public function show(User $usuario): JsonResponse
    {
        return response()->json([
            'user' => $usuario,
            'rolesSelected' => $usuario->roles->pluck('id'),
            'permisosSelected' => $usuario->permissions->pluck('id'),
        ]);
    }

    public function update(StoreUserRequest $request, User $usuario): JsonResponse
    {
        DB::transaction(function () use ($request, $usuario) {
            $usuario->update($this->datos($request));
            $this->sincronizarAccesos($usuario, $request);
        });

        return response()->json($usuario);
    }

    public function destroy(Request $request, User $usuario): Response
    {
        $this->impedirSobreSiMismo($request, $usuario, 'No podés eliminar tu propio usuario.');

        $usuario->delete();

        return response()->noContent();
    }

    /**
     * Dar de baja corta el acceso ya: además de impedir el próximo login
     * (findForPassport ignora inactivos), revoca las sesiones abiertas.
     */
    public function toggleActive(Request $request, User $usuario): JsonResponse
    {
        $this->impedirSobreSiMismo($request, $usuario, 'No podés darte de baja a vos mismo.');

        $usuario->update(['active' => ! $usuario->active]);

        if (! $usuario->active) {
            $usuario->tokens()->update(['revoked' => true]);
        }

        return response()->json(['id' => $usuario->id, 'active' => $usuario->active]);
    }

    public function sesiones(User $usuario): JsonResponse
    {
        $sesiones = $usuario->tokens()
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->get(['id', 'created_at', 'expires_at']);

        return response()->json($sesiones);
    }

    /**
     * El token se busca DENTRO de los del usuario de la URL: sin eso, con
     * cualquier ID se podría cerrar la sesión de otra persona.
     */
    public function revocarSesion(User $usuario, string $token): Response
    {
        /** @var Token $sesion */
        $sesion = $usuario->tokens()->whereKey($token)->firstOrFail();
        $sesion->refreshToken?->revoke();
        $sesion->revoke();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(StoreUserRequest $request): array
    {
        $datos = collect($request->validated('usuario'))
            ->only(['name', 'username', 'email', 'password', 'sede_id'])
            ->all();

        // Al editar, sin contraseña nueva se conserva la actual. El cast
        // `hashed` del modelo la hashea al guardar.
        if (empty($datos['password'])) {
            unset($datos['password']);
        }

        return $datos;
    }

    private function sincronizarAccesos(User $user, StoreUserRequest $request): void
    {
        $user->syncRoles($request->validated('usuario.rolesSelected', []));
        $user->syncPermissions($request->validated('usuario.permisosSelected', []));
    }

    /**
     * Darse de baja o borrarse a uno mismo deja al sistema sin quien lo
     * administre si es el último administrador.
     */
    private function impedirSobreSiMismo(Request $request, User $usuario, string $mensaje): void
    {
        if ($request->user()->is($usuario)) {
            throw ValidationException::withMessages(['usuario' => $mensaje]);
        }
    }
}
