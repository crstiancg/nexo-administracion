<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * El login NO pasa por acá: el front pide el token directo a POST /oauth/token
 * (password grant de Passport). Este controlador cubre lo que viene después.
 */
class AuthController extends Controller
{
    /**
     * Mismo contrato que muni-asis-sitra / sistema-botica: `permisos` junta los
     * directos del usuario y los heredados de sus roles, sin repetidos.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user->load('sede:id,nombre,direccion,telefono')->makeHidden(['roles', 'permissions']),
            'roles' => $user->getRoleNames(),
            'permisos' => $user->getAllPermissions()->pluck('name')->unique()->values(),
        ]);
    }

    /**
     * Pasar a operar en otra sede (quien atiende varias tiendas). Es un
     * permiso propio: un cajero queda fijo en la suya.
     */
    public function cambiarSede(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'sede_id' => ['required', 'integer', Rule::exists('sedes', 'id')->where('activo', true)],
        ], [], ['sede_id' => 'sede']);

        $request->user()->update(['sede_id' => $datos['sede_id']]);

        return response()->json(Sede::query()->find($datos['sede_id'], ['id', 'nombre', 'direccion', 'telefono']));
    }

    public function logout(Request $request): Response
    {
        $token = $request->user()->token();
        $token->refreshToken?->revoke();
        $token->revoke();

        return response()->noContent();
    }
}
