<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El único lugar que registra y anula compras. Registrar una compra ES la
 * entrada al inventario de la sede del usuario (con el costo, que recalcula
 * el costo promedio, y el lote si el producto lo maneja). Anularla saca la
 * misma mercadería de los mismos lotes: si ya se vendió, no se puede.
 */
class Compras
{
    public function __construct(private Inventario $inventario, private OrdenesCompra $ordenes) {}

    /**
     * @param  array<string, mixed>  $datos  compra validada (con `items`)
     */
    public function registrar(array $datos, User $usuario): Compra
    {
        return DB::transaction(function () use ($datos, $usuario) {
            $compra = new Compra;
            $compra->forceFill([
                'proveedor_id' => $datos['proveedor_id'],
                'sede_id' => $usuario->sedeOperativa(),
                'tipo_documento' => $datos['tipo_documento'],
                'numero_documento' => $datos['numero_documento'] ?? null,
                'fecha' => $datos['fecha'],
                'observacion' => $datos['observacion'] ?? null,
                'estado' => Compra::REGISTRADA,
                'total' => 0,
                'user_id' => $usuario->id,
            ])->save();
            $compra->forceFill(['codigo' => 'C-'.str_pad((string) $compra->id, 6, '0', STR_PAD_LEFT)])->save();

            $total = 0.0;
            foreach (array_values($datos['items']) as $item) {
                $subtotal = round((float) $item['cantidad'] * (float) $item['costo_unitario'], 2);
                $total += $subtotal;

                $compra->items()->create([
                    'variante_id' => $item['variante_id'],
                    'cantidad' => $item['cantidad'],
                    'costo_unitario' => $item['costo_unitario'],
                    'subtotal' => $subtotal,
                    'lote' => filled($item['lote'] ?? null) ? Inventario::normalizarLote($item['lote']) : null,
                    'vence_at' => $item['vence_at'] ?? null,
                ]);
            }
            $compra->forceFill(['total' => round($total, 2)])->save();

            $this->traducirErrores(fn () => $this->inventario->entrada(
                $compra->sede_id,
                array_map(fn ($item) => [
                    'variante_id' => $item['variante_id'],
                    'cantidad' => $item['cantidad'],
                    'costo_unitario' => $item['costo_unitario'],
                    'lote' => $item['lote'] ?? null,
                    'vence_at' => $item['vence_at'] ?? null,
                ], array_values($datos['items'])),
                $this->referencia($compra),
                null,
                $usuario,
                MovimientoInventario::MOTIVO_COMPRA,
                ['compra_id' => $compra->id],
            ));

            // Con orden de compra: queda recibida con esta compra.
            if (filled($datos['orden_compra_id'] ?? null)) {
                $this->ordenes->recibir((int) $datos['orden_compra_id'], $compra);
            }

            return $compra;
        });
    }

    /**
     * Saca del inventario lo que entró con la compra, de los mismos lotes.
     */
    public function anular(Compra $compra, string $motivo, User $usuario): Compra
    {
        return DB::transaction(function () use ($compra, $motivo, $usuario) {
            $compra = Compra::query()->whereKey($compra->id)->lockForUpdate()->firstOrFail();

            if ($compra->estado !== Compra::REGISTRADA) {
                throw ValidationException::withMessages(['estado' => 'Esta compra ya está anulada.'])->status(409);
            }

            $entradas = $compra->movimientos()
                ->where('motivo', MovimientoInventario::MOTIVO_COMPRA)
                ->with('lotes')
                ->orderBy('id')
                ->get();

            $lineas = $entradas->map(fn (MovimientoInventario $m) => [
                'variante_id' => $m->variante_id,
                'cantidad' => $m->cantidad,
                // Cada ítem entró a un solo lote: sale de ése.
                'lote_id' => $m->lotes->first()?->id,
            ])->all();

            try {
                $this->inventario->salida(
                    $compra->sede_id,
                    $lineas,
                    MovimientoInventario::MOTIVO_ANULACION_COMPRA,
                    $this->referencia($compra),
                    $motivo,
                    $usuario,
                    ['compra_id' => $compra->id],
                    vencidos: true,
                );
            } catch (ValidationException) {
                throw ValidationException::withMessages([
                    'estado' => 'No se puede anular: parte de la mercadería de esta compra ya salió (se vendió o se trasladó).',
                ])->status(409);
            }

            $compra->forceFill([
                'estado' => Compra::ANULADA,
                'anulada_at' => now(),
                'anulada_por' => $usuario->id,
                'motivo_anulacion' => $motivo,
            ])->save();

            return $compra;
        });
    }

    private function referencia(Compra $compra): string
    {
        return mb_substr(trim("{$compra->codigo} {$compra->documento()}"), 0, 60);
    }

    /**
     * Los errores del inventario vienen por línea (movimiento.lineas.N): se
     * muestran en el ítem de la compra.
     */
    private function traducirErrores(callable $accion): void
    {
        try {
            $accion();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn ($mensajes, $clave) => [str_replace('movimiento.lineas.', 'compra.items.', $clave) => $mensajes])
                ->all());
        }
    }
}
