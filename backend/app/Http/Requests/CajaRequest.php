<?php

namespace App\Http\Requests;

use App\Models\MovimientoCaja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Abrir, cerrar y registrar ingresos/egresos de caja. Las reglas dependen de
 * la ruta: son tres formularios chicos que comparten los montos.
 */
class CajaRequest extends FormRequest
{
    private const MONTO = ['numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'cajas.abrir' => [
                'caja.monto_apertura' => ['required', ...self::MONTO],
            ],
            'cajas.cerrar' => [
                'caja.monto_contado' => ['required', ...self::MONTO],
                'caja.observacion' => ['nullable', 'string', 'max:500'],
            ],
            'cajas.movimientos' => [
                'movimiento.tipo' => ['required', Rule::in([MovimientoCaja::INGRESO, MovimientoCaja::EGRESO])],
                'movimiento.monto' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
                'movimiento.concepto' => ['required', 'string', 'max:200'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'caja.monto_apertura' => 'monto inicial',
            'caja.monto_contado' => 'efectivo contado',
            'caja.observacion' => 'observación',
            'movimiento.tipo' => 'tipo',
            'movimiento.monto' => 'monto',
            'movimiento.concepto' => 'concepto',
        ];
    }
}
