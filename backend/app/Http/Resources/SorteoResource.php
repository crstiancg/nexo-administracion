<?php

namespace App\Http\Resources;

use App\Models\Cliente;
use App\Models\Sorteo;
use App\Models\SorteoPremio;
use App\Models\SorteoTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sorteo
 */
class SorteoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'bases' => $this->bases,
            'periodo' => $this->periodo,
            'inicia_el' => $this->inicia_el?->toDateString(),
            'termina_el' => $this->termina_el?->toDateString(),
            // Sugerido al vender tickets sueltos (en cada venta se elige).
            'precio_ticket' => $this->precio_ticket,
            'un_premio_por_cliente' => $this->un_premio_por_cliente,
            // programado | abierto | por_sortear | sorteando | sorteado | cancelado
            'estado' => $this->estadoVisible(),
            'editable' => $this->estado === Sorteo::ABIERTO,
            'se_puede_sortear' => $this->sePuedeSortear(),
            'sorteado_at' => $this->sorteado_at?->toIso8601String(),
            // Vacío = participan todas las sedes.
            'sedes' => $this->whenLoaded('sedes', fn () => $this->sedes->map->only(['id', 'nombre'])->values()),
            'tickets_count' => (int) ($this->tickets_vigentes_count ?? 0),
            'participantes_count' => (int) ($this->participantes_count ?? 0),
            'premios_count' => $this->whenCounted('premios'),
            'premios' => $this->whenLoaded('premios', fn () => $this->premios->map(fn (SorteoPremio $p) => self::premio($p))->values()),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Un premio con su ganador. `publico` es lo que se muestra en la
     * pantalla en vivo (se transmite): nombre corto y documento enmascarado.
     *
     * @return array<string, mixed>
     */
    public static function premio(SorteoPremio $premio): array
    {
        $ticket = $premio->ticket;

        return [
            'id' => $premio->id,
            'orden' => $premio->orden,
            'descripcion' => $premio->descripcion,
            'sorteado_at' => $premio->sorteado_at?->toIso8601String(),
            'sorteado_por' => $premio->relationLoaded('sorteadoPor') ? $premio->sorteadoPor?->name : null,
            'participantes' => $premio->participantes,
            'descartes' => $premio->descartes ?? [],
            'ganador' => $ticket ? [
                'ticket_id' => $ticket->id,
                'numero' => $ticket->numero,
                'codigo' => $ticket->codigo(),
                'pedido_id' => $ticket->pedido_id,
                'cliente' => $ticket->cliente?->only(['id', 'nombre', 'tipo_documento', 'numero_documento', 'telefono']),
                'publico' => self::publico($ticket),
            ] : null,
        ];
    }

    /** "Juan P." y "DNI ****5678": para la transmisión. */
    public static function publico(SorteoTicket $ticket): array
    {
        return [
            'codigo' => $ticket->codigo(),
            'nombre' => self::nombreCorto($ticket->cliente),
            'documento' => self::documentoOculto($ticket->cliente),
        ];
    }

    public static function nombreCorto(?Cliente $cliente): string
    {
        $partes = preg_split('/\s+/', trim((string) $cliente?->nombre)) ?: [];
        if (! $partes || $partes[0] === '') {
            return 'Cliente';
        }
        $nombre = mb_convert_case(mb_strtolower($partes[0]), MB_CASE_TITLE);

        return isset($partes[1]) ? $nombre.' '.mb_strtoupper(mb_substr($partes[1], 0, 1)).'.' : $nombre;
    }

    public static function documentoOculto(?Cliente $cliente): ?string
    {
        $numero = (string) $cliente?->numero_documento;
        if ($numero === '') {
            return null;
        }

        return trim(($cliente->tipo_documento ? $cliente->tipo_documento.' ' : '').str_repeat('*', max(0, mb_strlen($numero) - 4)).mb_substr($numero, -4));
    }
}
