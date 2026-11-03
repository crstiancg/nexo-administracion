<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use App\Services\ConsultaDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->generateViewSetList(
            $request,
            Cliente::query()->withCount('pedidos'),
            ['tipo_documento'],
            ['nombre', 'numero_documento', 'telefono'],
            ['id', 'nombre', 'numero_documento'],
            ClienteResource::class,
        );
    }

    public function store(StoreClienteRequest $request): JsonResponse
    {
        $cliente = Cliente::create($request->validated('cliente'));

        return response()->json((new ClienteResource($cliente))->resolve($request), 201);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return response()->json((new ClienteResource($cliente->loadCount('pedidos')))->resolve(request()));
    }

    public function update(StoreClienteRequest $request, Cliente $cliente): JsonResponse
    {
        $cliente->update($request->validated('cliente'));

        return response()->json((new ClienteResource($cliente))->resolve($request));
    }

    public function destroy(Cliente $cliente): JsonResponse|Response
    {
        // Sus pedidos son historial de ventas: no se pierde (FK restrict).
        if ($cliente->pedidos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene pedidos registrados.',
            ], 409);
        }

        $cliente->delete();

        return response()->noContent();
    }

    /**
     * Autocompletar al registrar: primero en la base (un cliente ya cargado
     * no gasta cupo de la API), después en RENIEC/SUNAT.
     *
     * `origen`: 'local' (ya existe, viene `cliente`), 'api' (viene `datos`
     * para completar el form) o null (no se encontró o la consulta no está
     * habilitada: se carga a mano).
     */
    public function consultarDocumento(Request $request, ConsultaDocumento $consulta): JsonResponse
    {
        $validado = $request->validate([
            'tipo' => ['required', Rule::in([Cliente::DNI, Cliente::RUC])],
            'numero' => ['required', 'string', $request->input('tipo') === Cliente::RUC ? 'digits:11' : 'digits:8'],
        ], [], ['numero' => 'número de documento']);

        $existente = Cliente::query()
            ->where('tipo_documento', $validado['tipo'])
            ->where('numero_documento', $validado['numero'])
            ->first();

        if ($existente) {
            return response()->json(['origen' => 'local', 'cliente' => (new ClienteResource($existente))->resolve($request)]);
        }

        $datos = $consulta->consultar($validado['tipo'], $validado['numero']);

        return response()->json([
            'origen' => $datos ? 'api' : null,
            'datos' => $datos,
            'consulta_habilitada' => $consulta->habilitada(),
        ]);
    }
}
