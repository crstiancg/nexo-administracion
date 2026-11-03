<?php

namespace App\Http\Controllers;

use App\Models\OrdenServicio;
use App\Support\QrCode;
use Illuminate\Contracts\View\View;

/**
 * Documentos impresos (HTML para imprimir o guardar en PDF desde el
 * navegador). Se abren por un enlace firmado y temporal que entrega la API a
 * quien tiene permiso: la pestaña nueva no lleva el token de la sesión.
 */
class ImpresionController extends Controller
{
    public const FORMATOS = ['orden', 'etiqueta'];

    public function ordenServicio(OrdenServicio $orden, string $formato): View
    {
        $orden->load([
            'cliente', 'sede', 'tecnico:id,name', 'usuario:id,name', 'tipoEquipo:id,nombre', 'marca:id,nombre',
            'pedido.items' => fn ($q) => $q->orderBy('id'),
            'pedido.items.variante.producto:id,nombre',
            'pedido.pagos',
        ]);

        $total = (float) ($orden->pedido?->total ?? 0);
        $pagado = round((float) ($orden->pedido?->pagos->sum('monto') ?? 0), 2);

        return view("impresion.{$formato}-servicio", [
            'orden' => $orden,
            'empresa' => config('empresa'),
            'qr' => QrCode::svg($orden->urlSeguimiento()),
            'repuestos' => $orden->pedido?->items->whereNotNull('variante_id') ?? collect(),
            'total' => $total,
            'pagado' => $pagado,
            'saldo' => round($total - $pagado, 2),
            'estado' => OrdenServicio::ESTADOS[$orden->estado] ?? $orden->estado,
            'seguridad' => OrdenServicio::SEGURIDAD[$orden->seguridad_tipo] ?? $orden->seguridad_tipo,
        ]);
    }
}
