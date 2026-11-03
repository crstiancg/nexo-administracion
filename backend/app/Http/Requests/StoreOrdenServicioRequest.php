<?php

namespace App\Http\Requests;

use App\Models\OrdenServicio;
use App\Models\Pago;
use App\Support\Cantidades;
use App\Support\Sedes;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y edita una orden de servicio. Payload anidado en `orden`. Los totales
 * no vienen: el pedido de cobro lo arma App\Services\OrdenesServicio. El stock
 * de los repuestos se valida recién al entregar (ahí salen del inventario).
 */
class StoreOrdenServicioRequest extends FormRequest
{
    public const MAX_REPUESTOS = 50;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $orden = $this->input('orden');
        if (! is_array($orden)) {
            return;
        }

        foreach (['modelo', 'serie', 'accesorios', 'seguridad_valor', 'falla', 'diagnostico', 'observacion'] as $campo) {
            if (is_string($orden[$campo] ?? null)) {
                $orden[$campo] = trim($orden[$campo]) ?: null;
            }
        }
        foreach (['mano_obra', 'garantia_dias'] as $campo) {
            if (in_array($orden[$campo] ?? null, [null, ''], true)) {
                $orden[$campo] = 0;
            }
        }
        if (is_array($orden['anticipo'] ?? null) && is_string($orden['anticipo']['referencia'] ?? null)) {
            $orden['anticipo']['referencia'] = trim($orden['anticipo']['referencia']) ?: null;
        }

        $this->merge(['orden' => $orden]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'orden.cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'orden.tecnico_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('active', true)],
            'orden.tipo_equipo_id' => ['required', 'integer', Rule::exists('tipos_equipo', 'id')->where('activo', true)],
            'orden.marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
            'orden.modelo' => ['nullable', 'string', 'max:80'],
            'orden.serie' => ['nullable', 'string', 'max:60'],
            'orden.accesorios' => ['nullable', 'string', 'max:250'],
            'orden.seguridad_tipo' => ['required', Rule::in(array_keys(OrdenServicio::SEGURIDAD))],
            'orden.seguridad_valor' => [
                Rule::requiredIf(fn () => $this->input('orden.seguridad_tipo', 'ninguno') !== 'ninguno'),
                'nullable', 'string', 'max:100',
            ],
            'orden.checklist' => ['nullable', 'array', 'max:30'],
            'orden.checklist.*' => ['boolean'],
            'orden.falla' => ['required', 'string', 'max:1000'],
            'orden.diagnostico' => ['nullable', 'string', 'max:2000'],
            'orden.observacion' => ['nullable', 'string', 'max:500'],
            'orden.es_garantia' => ['boolean'],
            'orden.garantia_dias' => ['integer', 'min:0', 'max:1095'],
            'orden.mano_obra' => ['numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'orden.repuestos' => ['present', 'array', 'max:'.self::MAX_REPUESTOS],
        ];

        foreach (array_keys((array) $this->input('orden.repuestos', [])) as $i) {
            $varianteId = $this->input("orden.repuestos.{$i}.variante_id");
            $rules["orden.repuestos.{$i}.variante_id"] = [
                'required', 'integer', 'exists:variantes,id', $this->varianteUnica($i),
                Sedes::habilitada($varianteId, $this->user()?->sede_id),
            ];
            $rules["orden.repuestos.{$i}.cantidad"] = [...Cantidades::positiva(), Cantidades::segunUnidad($varianteId)];
            $rules["orden.repuestos.{$i}.precio_unitario"] = ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
        }

        // El adelanto sólo al recibir el equipo; después se abona desde la orden.
        if ($this->isMethod('post') && (float) $this->input('orden.anticipo.monto', 0) > 0) {
            $rules['orden.anticipo.metodo'] = ['required', Rule::in(array_keys(Pago::METODOS))];
            $rules['orden.anticipo.monto'] = ['numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2', $this->anticipoNoMayorAlTotal()];
            $rules['orden.anticipo.recibido'] = ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
            $rules['orden.anticipo.referencia'] = [
                Rule::requiredIf(in_array($this->input('orden.anticipo.metodo'), CobrarPedidoRequest::CON_OPERACION, true)),
                'nullable', 'string', 'max:40',
            ];
        }

        return $rules;
    }

    private function varianteUnica(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('orden.repuestos', []) as $i => $otro) {
                if ($i === $indice) {
                    return;
                }
                if (($otro['variante_id'] ?? null) == $value) {
                    $fail('Este repuesto ya está en otra línea: suma la cantidad ahí.');

                    return;
                }
            }
        };
    }

    private function anticipoNoMayorAlTotal(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $total = (float) $this->input('orden.mano_obra', 0) + collect((array) $this->input('orden.repuestos', []))
                ->sum(fn ($r) => round((float) ($r['cantidad'] ?? 0) * (float) ($r['precio_unitario'] ?? 0), 2));

            if ((float) $value > round($total, 2)) {
                $fail('El adelanto no puede ser mayor que el total de la orden.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orden.cliente_id.required' => 'Elige el cliente dueño del equipo.',
            'orden.tipo_equipo_id.required' => 'Elige qué tipo de equipo es.',
            'orden.falla.required' => 'Describe la falla que reporta el cliente.',
            'orden.seguridad_valor.required' => 'Anota la clave o el patrón para poder probar el equipo.',
            'orden.anticipo.referencia.required' => 'Anota el número de operación para poder verificar el pago.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'orden.cliente_id' => 'cliente',
            'orden.tecnico_id' => 'técnico',
            'orden.tipo_equipo_id' => 'tipo de equipo',
            'orden.marca_id' => 'marca',
            'orden.modelo' => 'modelo',
            'orden.serie' => 'serie / IMEI',
            'orden.accesorios' => 'accesorios',
            'orden.seguridad_tipo' => 'tipo de bloqueo',
            'orden.seguridad_valor' => 'clave',
            'orden.falla' => 'falla',
            'orden.diagnostico' => 'diagnóstico',
            'orden.observacion' => 'observación',
            'orden.garantia_dias' => 'garantía',
            'orden.mano_obra' => 'mano de obra',
            'orden.repuestos.*.variante_id' => 'repuesto',
            'orden.repuestos.*.cantidad' => 'cantidad',
            'orden.repuestos.*.precio_unitario' => 'precio',
            'orden.anticipo.metodo' => 'método',
            'orden.anticipo.monto' => 'adelanto',
            'orden.anticipo.referencia' => 'n° de operación',
        ];
    }
}
