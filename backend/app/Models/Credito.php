<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Fechas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El saldo de un pedido financiado en cuotas. Se otorga y se arma sólo en
 * App\Services\Creditos. Lo pagado sale de los pagos del pedido (después de
 * otorgarlo) y cubre las cuotas de la más antigua a la más nueva.
 */
#[Fillable(['frecuencia', 'numero_cuotas', 'observacion'])]
class Credito extends Model
{
    use Auditable;

    /** "Por vencer": la próxima cuota vence hoy o en estos días (para recordarla). */
    public const DIAS_POR_VENCER = 3;

    public const FRECUENCIAS = [
        'semanal' => 'Semanal',
        'quincenal' => 'Quincenal',
        'mensual' => 'Mensual',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'pagado_inicial' => 'decimal:2',
            'numero_cuotas' => 'integer',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(CreditoCuota::class)->orderBy('numero');
    }

    /**
     * La próxima cuota (sin vencer) vence hoy o en DIAS_POR_VENCER días:
     * para recordarla antes de que se atrase.
     *
     * @param  array<string, mixed>|null  $proxima  una de estadoCuotas()
     */
    public static function proximaPorVencer(?array $proxima): bool
    {
        return $proxima !== null
            && $proxima['estado'] !== 'vencida'
            && $proxima['vence'] <= Fechas::hoy()->addDays(self::DIAS_POR_VENCER)->toDateString();
    }

    /** Lo abonado al crédito: lo pagado del pedido desde que se otorgó. */
    public function pagado(?float $pagadoPedido = null): float
    {
        $pagadoPedido ??= $this->pedido->pagado();

        return round(max(0, $pagadoPedido - (float) $this->pagado_inicial), 2);
    }

    public function saldo(?float $pagadoPedido = null): float
    {
        return round(max(0, (float) $this->monto - $this->pagado($pagadoPedido)), 2);
    }

    /**
     * Cada cuota con lo que tiene cubierto y su estado (pagada, parcial,
     * pendiente o vencida), aplicando lo pagado de la más antigua a la más nueva.
     *
     * @return array<int, array<string, mixed>>
     */
    public function estadoCuotas(?float $pagadoPedido = null): array
    {
        $disponible = $this->pagado($pagadoPedido);
        $hoy = Fechas::hoyIso();

        return $this->cuotas->map(function (CreditoCuota $cuota) use (&$disponible, $hoy) {
            $monto = (float) $cuota->monto;
            $cubierto = round(min($monto, $disponible), 2);
            $disponible = round($disponible - $cubierto, 2);
            $falta = round($monto - $cubierto, 2);
            $vence = $cuota->vence->toDateString();

            return [
                'id' => $cuota->id,
                'numero' => $cuota->numero,
                'vence' => $vence,
                'monto' => number_format($monto, 2, '.', ''),
                'pagado' => number_format($cubierto, 2, '.', ''),
                'falta' => number_format($falta, 2, '.', ''),
                'estado' => match (true) {
                    $falta <= 0 => 'pagada',
                    $vence < $hoy => 'vencida',
                    $cubierto > 0 => 'parcial',
                    default => 'pendiente',
                },
            ];
        })->all();
    }
}
