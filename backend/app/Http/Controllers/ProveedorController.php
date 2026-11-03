<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProveedorRequest;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Services\ConsultaDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ruta en routes/api.php con ->parameters(['proveedores' => 'proveedor']):
 * sin eso el parámetro sale {proveedore}.
 */
class ProveedorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->generateViewSetList(
            $request,
            Proveedor::query()->withCount('compras'),
            ['activo'],
            ['razon_social', 'ruc', 'contacto'],
            ['id', 'razon_social', 'ruc', 'activo'],
        );
    }

    public function store(StoreProveedorRequest $request): JsonResponse
    {
        return response()->json(Proveedor::create($request->validated('proveedor')), 201);
    }

    public function show(Proveedor $proveedor): JsonResponse
    {
        return response()->json($proveedor);
    }

    public function update(StoreProveedorRequest $request, Proveedor $proveedor): JsonResponse
    {
        $proveedor->update($request->validated('proveedor'));

        return response()->json($proveedor);
    }

    public function destroy(Proveedor $proveedor): JsonResponse|Response
    {
        // Sus compras son historial (FK restrict).
        if ($proveedor->compras()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene compras registradas. Desactívelo en su lugar.',
            ], 409);
        }

        $proveedor->delete();

        return response()->noContent();
    }

    /**
     * Autocompletar con SUNAT: primero en la base, después en la API.
     * `origen`: 'local' (ya existe), 'api' (viene `datos`) o null (se carga a mano).
     */
    public function consultarRuc(Request $request, ConsultaDocumento $consulta): JsonResponse
    {
        $ruc = $request->validate(['ruc' => ['required', 'digits:11']], [], ['ruc' => 'RUC'])['ruc'];

        if ($existente = Proveedor::query()->where('ruc', $ruc)->first()) {
            return response()->json(['origen' => 'local', 'proveedor' => $existente]);
        }

        $datos = $consulta->consultar(Cliente::RUC, $ruc);

        return response()->json([
            'origen' => $datos ? 'api' : null,
            'datos' => $datos,
            'consulta_habilitada' => $consulta->habilitada(),
        ]);
    }
}
