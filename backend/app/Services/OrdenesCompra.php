<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\OrdenCompra;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El único lugar que cambia una orden de compra. No toca stock: la
 * mercadería entra con la compra que la recibe (App\Services\Compras).
 */
class OrdenesCompra
{
    /**
     * Crea o reemplaza los ítems de una orden PENDIENTE y recalcula el total
     * estimado (nunca se confía en el del navegador).
     *
     * @param  array<string, mixed>  $datos  orden validada (con `items`)
     */
    public function guardar(OrdenCompra $orden, array $datos, User $usuario): OrdenCompra
    {
        return DB::transaction(function () use ($orden, $datos, $usuario) {
            $nueva = ! $orden->exists;
            $orden->fill($datos);

            if ($nueva) {
                $orden->forceFill([
                    'estado' => OrdenCompra::PENDIENTE,
                    'sede_id' => $usuario->sedeOperativa(),
                    'user_id' => $usuario->id,
                    'total' => 0,
                ]);
            }
            $orden->save();

            if ($nueva) {
                $orden->forceFill(['codigo' => 'OC-'.str_pad((string) $orden->id, 6, '0', STR_PAD_LEFT)])->save();
            }

            $orden->items()->delete();
            $total = 0.0;
            foreach ($datos['items'] as $item) {
                $costo = filled($item['costo_unitario'] ?? null) ? (float) $item['costo_unitario'] : null;
                $linea = $costo === null ? 0.0 : round((float) $item['cantidad'] * $costo, 2);
                $total += $linea;

                $orden->items()->create([
                    'variante_id' => $item['variante_id'],
                    'cantidad' => $item['cantidad'],
                    'costo_unitario' => $costo,
                    'subtotal' => $linea,
                ]);
            }

            $orden->forceFill(['total' => round($total, 2)])->save();

            return $orden;
        });
    }

    /** Ya se le mandó al proveedor: deja de editarse. */
    public function enviar(OrdenCompra $orden): OrdenCompra
    {
        return DB::transaction(function () use ($orden) {
            $orden = $this->bloquear($orden);
            if ($orden->estado !== OrdenCompra::PENDIENTE) {
                $this->falla("Un pedido de compra {$orden->estadoTexto()} no se marca como enviado.");
            }

            $orden->forceFill(['estado' => OrdenCompra::ENVIADA, 'enviada_at' => now()])->save();

            return $orden;
        });
    }

    public function anular(OrdenCompra $orden, string $motivo): OrdenCompra
    {
        return DB::transaction(function () use ($orden, $motivo) {
            $orden = $this->bloquear($orden);
            if (! $orden->abierta()) {
                $this->falla("Un pedido de compra {$orden->estadoTexto()} no se anula.");
            }

            $orden->forceFill([
                'estado' => OrdenCompra::ANULADA,
                'anulada_at' => now(),
                'motivo_anulacion' => $motivo,
            ])->save();

            return $orden;
        });
    }

    /**
     * La compra que la recibe. Lo llama App\Services\Compras dentro de la
     * misma transacción que registra la compra.
     */
    public function recibir(int $ordenId, Compra $compra): void
    {
        $orden = OrdenCompra::query()->whereKey($ordenId)->lockForUpdate()->firstOrFail();
        if (! $orden->abierta()) {
            $this->falla("El pedido de compra {$orden->codigo} ya está {$orden->estadoTexto()}.", 'compra.orden_compra_id');
        }

        $orden->forceFill(['estado' => OrdenCompra::RECIBIDA, 'compra_id' => $compra->id])->save();
    }

    private function bloquear(OrdenCompra $orden): OrdenCompra
    {
        return OrdenCompra::query()->whereKey($orden->id)->lockForUpdate()->firstOrFail();
    }

    private function falla(string $mensaje, string $campo = 'estado'): never
    {
        throw ValidationException::withMessages([$campo => $mensaje])->status(409);
    }
}
