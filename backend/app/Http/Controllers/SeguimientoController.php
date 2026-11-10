<?php

namespace App\Http\Controllers;

use App\Models\EstadoOrden;
use App\Models\OrdenServicio;
use App\Support\Empresa;
use Illuminate\Http\JsonResponse;

/**
 * Página pública de seguimiento: el cliente escanea el QR del ticket (o abre
 * el enlace que le llegó por WhatsApp) y ve cómo va su equipo. Sin sesión, por
 * el token de la orden; sólo lo que el cliente necesita saber (nada de la
 * clave del equipo, ni del técnico, ni de los costos internos).
 */
class SeguimientoController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $orden = OrdenServicio::query()
            ->where('token', $token)
            ->with([
                'sede:id,nombre,direccion,telefono',
                'tipoEquipo:id,nombre',
                'marca:id,nombre',
                'movimientos' => fn ($q) => $q->orderBy('id'),
                'pedido' => fn ($q) => $q->select('id', 'total')->withSum('pagos', 'monto'),
            ])
            ->firstOrFail();

        $estado = EstadoOrden::deClave($orden->estado);
        $total = (float) ($orden->pedido?->total ?? 0);
        $pagado = round((float) ($orden->pedido?->pagos_sum_monto ?? 0), 2);

        return response()->json([
            'empresa' => Empresa::datos()['nombre'],
            'logo_url' => Empresa::datos()['logo_url'],
            'telefono_empresa' => Empresa::datos()['telefono'],
            'codigo' => $orden->codigo,
            'estado' => $orden->estado,
            'estado_label' => EstadoOrden::nombreDe($orden->estado),
            // Para armar los pasos sin sesión (no hay Configuración cargada).
            'estado_tipo' => $estado?->tipo,
            'estado_icono' => $estado?->icono,
            'estado_color' => $estado?->color,
            'mensaje' => $estado?->mensaje_cliente,
            'equipo' => $orden->equipo(),
            'falla' => $orden->falla,
            'sede' => $orden->sede?->only(['nombre', 'direccion', 'telefono']),
            'recibido_at' => $orden->recibido_at?->toIso8601String(),
            'entregado_at' => $orden->entregado_at?->toIso8601String(),
            'garantia_hasta' => $orden->garantiaHasta()?->toDateString(),
            'total' => number_format($total, 2, '.', ''),
            'saldo' => number_format($total - $pagado, 2, '.', ''),
            'movimientos' => $orden->movimientos->map(fn ($m) => [
                'estado' => $m->estado,
                'estado_label' => EstadoOrden::nombreDe($m->estado),
                'tipo' => EstadoOrden::deClave($m->estado)?->tipo,
                'fecha' => $m->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
