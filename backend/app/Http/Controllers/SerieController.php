<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Serie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Números de serie: la lista para elegir qué unidad sale (venta, merma,
 * traslado) y la consulta de una serie (garantías): de qué compra entró, a
 * quién se vendió y hasta cuándo tiene garantía.
 */
class SerieController extends Controller
{
    /**
     * `variante_id` + `sede_id` (por defecto la del usuario) y `estado`
     * (en_stock): las que se pueden elegir. `search`: por número, en todo
     * el catálogo (la consulta de garantías).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'variante_id' => ['nullable', 'integer'],
            'estado' => ['nullable', 'in:en_stock,vendida,baja'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $series = Serie::query()
            ->with(['variante:id,producto_id,presentacion,sku', 'variante.producto:id,nombre', 'sede:id,nombre'])
            ->when($request->filled('variante_id'), function ($q) use ($request) {
                $q->where('variante_id', $request->integer('variante_id'))
                    ->where('sede_id', $request->integer('sede_id') ?: $request->user()->sede_id);
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('search'), fn ($q) => $q->where('numero', 'like', '%'.Serie::normalizar($request->input('search')).'%'))
            ->orderBy($request->filled('variante_id') ? 'id' : 'numero')
            ->limit($request->filled('variante_id') ? 500 : 50)
            ->get();

        return response()->json(['data' => $series->map(fn (Serie $s) => [
            'id' => $s->id,
            'numero' => $s->numero,
            'estado' => $s->estado,
            'sede' => $s->sede?->nombre,
            'producto' => $s->variante->producto->nombre,
            'presentacion' => $s->variante->presentacion,
            'sku' => $s->variante->sku,
        ])]);
    }

    /** Toda la historia de una unidad y su garantía. */
    public function show(Serie $serie): JsonResponse
    {
        $serie->load([
            'variante:id,producto_id,presentacion,sku',
            'variante.producto:id,nombre,garantia_meses',
            'sede:id,nombre',
            'movimientos.sede:id,nombre',
            'movimientos.usuario:id,name',
            'movimientos.compra:id,codigo,proveedor_id',
            'movimientos.compra.proveedor:id,razon_social',
            'movimientos.pedido:id,codigo,cliente_id',
            'movimientos.pedido.cliente:id,nombre,telefono',
        ]);

        $venta = $serie->movimientos->last(fn (MovimientoInventario $m) => $m->motivo === MovimientoInventario::MOTIVO_VENTA);
        $vendida = $serie->estado === Serie::VENDIDA && $venta;
        $meses = $serie->variante->producto->garantia_meses;
        $garantiaHasta = $vendida && $meses ? Carbon::parse($venta->created_at)->addMonths($meses) : null;

        return response()->json([
            'id' => $serie->id,
            'numero' => $serie->numero,
            'estado' => $serie->estado,
            'sede' => $serie->sede?->nombre,
            'producto' => $serie->variante->producto->nombre,
            'presentacion' => $serie->variante->presentacion,
            'sku' => $serie->variante->sku,
            'venta' => $vendida ? [
                'pedido_id' => $venta->pedido?->id,
                'pedido' => $venta->pedido?->codigo,
                'cliente' => $venta->pedido?->cliente?->only(['id', 'nombre', 'telefono']),
                'fecha' => $venta->created_at->toIso8601String(),
            ] : null,
            'garantia_meses' => $meses,
            'garantia_hasta' => $garantiaHasta?->toDateString(),
            'en_garantia' => $garantiaHasta?->isFuture(),
            'historia' => $serie->movimientos->map(fn (MovimientoInventario $m) => [
                'fecha' => $m->created_at->toIso8601String(),
                'tipo' => $m->tipo,
                'motivo' => $m->motivo,
                'referencia' => $m->referencia,
                'sede' => $m->sede?->nombre,
                'usuario' => $m->usuario?->name,
                'proveedor' => $m->compra?->proveedor?->razon_social,
                'cliente' => $m->pedido?->cliente?->nombre,
                'entra' => (float) $m->cantidad > 0,
            ])->values(),
        ]);
    }
}
