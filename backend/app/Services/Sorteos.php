<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\Sorteo;
use App\Models\SorteoPremio;
use App\Models\SorteoTicket;
use App\Models\User;
use App\Support\Fechas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El único lugar que crea tickets y sortea premios.
 *
 * Tickets: una venta ENTREGADA (pagada, o a crédito) de un cliente
 * registrado da 1 ticket por cada `monto_por_ticket` soles del total, en
 * cada sorteo abierto de su sede. Si la venta se anula, sus tickets también
 * (mientras el sorteo no haya empezado a sortearse).
 *
 * Premios: cada ticket vigente es una chance (quien compró más tiene más).
 * El ganador lo elige el servidor (random_int, criptográfico): la pantalla
 * en vivo sólo anima hasta el número que salió.
 */
class Sorteos
{
    /**
     * Emite los tickets de una venta. Idempotente: si ya tiene tickets en un
     * sorteo, no le da otros.
     *
     * @return Collection<int, SorteoTicket>
     */
    public function emitirPorVenta(Pedido $pedido): Collection
    {
        if (! $pedido->cliente_id || $pedido->estado !== Pedido::ENTREGADO) {
            return collect();
        }

        $hoy = Fechas::hoy()->toDateString();
        $sorteos = Sorteo::query()
            ->where('estado', Sorteo::ABIERTO)
            ->whereDate('inicia_el', '<=', $hoy)
            ->whereDate('termina_el', '>=', $hoy)
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('sedes')
                ->orWhereHas('sedes', fn (Builder $s) => $s->whereKey($pedido->sede_id)))
            ->pluck('id');

        $emitidos = collect();
        foreach ($sorteos as $id) {
            $emitidos = $emitidos->merge(DB::transaction(function () use ($id, $pedido) {
                // Bloqueado: dos ventas a la vez no sacan el mismo número.
                $sorteo = Sorteo::query()->whereKey($id)->lockForUpdate()->first();
                if (! $sorteo?->recibeTickets() || $sorteo->tickets()->where('pedido_id', $pedido->id)->exists()) {
                    return [];
                }

                $cantidad = $sorteo->ticketsPara((float) $pedido->total);
                if ($cantidad < 1) {
                    return [];
                }

                $siguiente = (int) $sorteo->tickets()->max('numero') + 1;
                $tickets = [];
                for ($n = 0; $n < $cantidad; $n++) {
                    $ticket = new SorteoTicket;
                    $ticket->forceFill([
                        'sorteo_id' => $sorteo->id,
                        'numero' => $siguiente + $n,
                        'cliente_id' => $pedido->cliente_id,
                        'pedido_id' => $pedido->id,
                        'sede_id' => $pedido->sede_id,
                    ])->save();
                    $tickets[] = $ticket;
                }

                return $tickets;
            }));
        }

        return $emitidos;
    }

    /**
     * La venta se deshizo: sus tickets dejan de participar. Si el sorteo ya
     * empezó a sortearse, quedan como están (el acta no se reescribe).
     */
    public function anularDeVenta(Pedido $pedido): void
    {
        SorteoTicket::query()
            ->where('pedido_id', $pedido->id)
            ->vigentes()
            ->whereHas('sorteo', fn (Builder $q) => $q->where('estado', Sorteo::ABIERTO))
            ->update(['anulado_at' => now()]);
    }

    /**
     * Sortea un premio: un ticket vigente al azar. Con "un premio por
     * cliente", no entran los de quien ya ganó otro; nunca los descartados.
     */
    public function sortear(Sorteo $sorteo, SorteoPremio $premio, User $usuario): SorteoPremio
    {
        return DB::transaction(function () use ($sorteo, $premio, $usuario) {
            $sorteo = Sorteo::query()->whereKey($sorteo->id)->lockForUpdate()->firstOrFail();
            $premio = $sorteo->premios()->whereKey($premio->id)->lockForUpdate()->firstOrFail();

            if (! $sorteo->sePuedeSortear()) {
                throw ValidationException::withMessages([
                    'sorteo' => match (true) {
                        $sorteo->estado === Sorteo::CANCELADO => 'El sorteo está cancelado.',
                        $sorteo->estado === Sorteo::SORTEADO => 'Ya se sortearon todos los premios.',
                        default => 'Se sortea desde el último día ('.$sorteo->termina_el->format('d/m/Y').'): hasta entonces siguen entrando tickets.',
                    },
                ])->status(409);
            }
            if ($premio->ticket_id) {
                throw ValidationException::withMessages(['premio' => 'Este premio ya tiene ganador.'])->status(409);
            }

            $candidatos = $this->candidatos($sorteo);
            $total = (clone $candidatos)->count();
            if ($total === 0) {
                throw ValidationException::withMessages(['premio' => 'No quedan tickets que puedan ganar este premio.']);
            }

            $ganador = (clone $candidatos)->orderBy('sorteo_tickets.id')->offset(random_int(0, $total - 1))->first();

            $premio->forceFill([
                'ticket_id' => $ganador->id,
                'sorteado_at' => now(),
                'sorteado_por' => $usuario->id,
                'participantes' => $total,
            ])->save();

            $faltan = $sorteo->premios()->whereNull('ticket_id')->exists();
            $sorteo->forceFill([
                'estado' => $faltan ? Sorteo::SORTEANDO : Sorteo::SORTEADO,
                'sorteado_at' => $faltan ? $sorteo->sorteado_at : now(),
            ])->save();

            return $premio->load(['ticket.cliente', 'sorteadoPor:id,name']);
        });
    }

    /**
     * El ganador no se presentó (o no cumple las bases): queda en el acta y
     * el premio vuelve a sortearse sin su ticket.
     */
    public function descartar(Sorteo $sorteo, SorteoPremio $premio, string $motivo, User $usuario): SorteoPremio
    {
        return DB::transaction(function () use ($sorteo, $premio, $motivo, $usuario) {
            $sorteo = Sorteo::query()->whereKey($sorteo->id)->lockForUpdate()->firstOrFail();
            $premio = $sorteo->premios()->whereKey($premio->id)->with('ticket.cliente')->lockForUpdate()->firstOrFail();

            if (! $premio->ticket_id || $sorteo->estado === Sorteo::CANCELADO) {
                throw ValidationException::withMessages(['premio' => 'Este premio no tiene ganador que descartar.'])->status(409);
            }

            $premio->forceFill([
                'descartes' => [...($premio->descartes ?? []), [
                    'ticket_id' => $premio->ticket_id,
                    'numero' => $premio->ticket?->numero,
                    'cliente' => $premio->ticket?->cliente?->nombre,
                    'motivo' => $motivo,
                    'user' => $usuario->name,
                    'at' => now()->toIso8601String(),
                ]],
                'ticket_id' => null,
                'sorteado_at' => null,
                'sorteado_por' => null,
                'participantes' => null,
            ])->save();

            // Falta este premio: vuelve a "sorteando" (no entran tickets nuevos).
            $sorteo->forceFill(['estado' => Sorteo::SORTEANDO, 'sorteado_at' => null])->save();

            return $premio->load(['ticket.cliente', 'sorteadoPor:id,name']);
        });
    }

    /** Los tickets que pueden ganar el próximo premio. */
    public function candidatos(Sorteo $sorteo): Builder
    {
        $premios = $sorteo->premios()->get(['ticket_id', 'descartes']);
        $descartados = $premios->flatMap(fn ($p) => array_column($p->descartes ?? [], 'ticket_id'))->filter()->all();
        $ganadores = $premios->pluck('ticket_id')->filter()->all();

        return SorteoTicket::query()
            ->where('sorteo_id', $sorteo->id)
            ->vigentes()
            ->whereNotIn('sorteo_tickets.id', [...$descartados, ...$ganadores])
            ->when($sorteo->un_premio_por_cliente && $ganadores, fn (Builder $q) => $q->whereNotIn(
                'cliente_id',
                SorteoTicket::query()->whereKey($ganadores)->select('cliente_id'),
            ));
    }
}
