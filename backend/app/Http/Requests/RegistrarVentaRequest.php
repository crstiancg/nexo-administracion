<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Variante;
use App\Services\Facturacion;
use App\Support\Cantidades;
use App\Support\Sedes;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Venta de mostrador (punto de venta): ítems y pagos en un solo envío.
 * Payload anidado en `venta`. Se cobra TODO en el acto: la suma de los pagos
 * tiene que ser exactamente el total (para adelantos está el flujo de
 * pedidos).
 */
class RegistrarVentaRequest extends FormRequest
{
    public const MAX_ITEMS = 100;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $venta = $this->input('venta');
        if (! is_array($venta)) {
            return;
        }

        if (($venta['descuento'] ?? null) === null || $venta['descuento'] === '') {
            $venta['descuento'] = 0;
        }

        if (is_array($venta['pagos'] ?? null)) {
            $venta['pagos'] = array_map(function ($pago) {
                if (is_array($pago) && is_string($pago['referencia'] ?? null)) {
                    $pago['referencia'] = trim($pago['referencia']) ?: null;
                }

                return $pago;
            }, $venta['pagos']);
        }

        $this->merge(['venta' => $venta]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'venta.cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'venta.descuento' => ['numeric', 'min:0', 'max:99999999.99', 'decimal:0,2', $this->descuentoValido()],
            'venta.items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'venta.pagos' => ['required', 'array', 'min:1', 'max:5', $this->pagosCubrenElTotal()],
            // ticket (sin comprobante SUNAT) | boleta | factura
            'venta.comprobante' => ['nullable', Rule::in(['ticket', 'boleta', 'factura']), $this->comprobanteValido()],
        ];

        foreach (array_keys((array) $this->input('venta.items', [])) as $i) {
            $rules["venta.items.{$i}.variante_id"] = [
                'required', 'integer', 'exists:variantes,id', $this->varianteUnica($i), $this->productoActivo(),
                Sedes::habilitada($this->input("venta.items.{$i}.variante_id"), $this->user()?->sede_id),
            ];
            $rules["venta.items.{$i}.cantidad"] = [...Cantidades::positiva(10000), Cantidades::segunUnidad($this->input("venta.items.{$i}.variante_id"))];
            $rules["venta.items.{$i}.precio_unitario"] = ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
        }

        foreach (array_keys((array) $this->input('venta.pagos', [])) as $j) {
            $metodo = $this->input("venta.pagos.{$j}.metodo");
            $rules["venta.pagos.{$j}.metodo"] = ['required', Rule::in(array_keys(Pago::METODOS))];
            $rules["venta.pagos.{$j}.monto"] = ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'];
            $rules["venta.pagos.{$j}.recibido"] = ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
            $rules["venta.pagos.{$j}.referencia"] = [
                Rule::requiredIf(in_array($metodo, CobrarPedidoRequest::CON_OPERACION, true)),
                'nullable', 'string', 'max:40',
            ];
        }

        return $rules;
    }

    private function total(): float
    {
        $subtotal = collect((array) $this->input('venta.items', []))
            ->sum(fn ($i) => round((float) ($i['cantidad'] ?? 0) * (float) ($i['precio_unitario'] ?? 0), 2));

        return round($subtotal - (float) $this->input('venta.descuento', 0), 2);
    }

    /** El tipo pedido en el POS → código SUNAT (null = ticket, sin comprobante). */
    public const COMPROBANTES = ['boleta' => Comprobante::BOLETA, 'factura' => Comprobante::FACTURA];

    /**
     * Antes de vender: factura sólo con cliente con RUC y boleta de S/ 700 o
     * más con el documento del cliente (si no, la venta se haría y el
     * comprobante fallaría).
     */
    private function comprobanteValido(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $tipo = self::COMPROBANTES[$value] ?? null;
            if (! $tipo) {
                return;
            }

            $cliente = $this->input('venta.cliente_id') ? Cliente::find($this->input('venta.cliente_id')) : null;
            try {
                app(Facturacion::class)->validar(new Pedido, $tipo, $cliente, $this->total());
            } catch (ValidationException $e) {
                $fail(collect($e->errors())->flatten()->first());
            }
        };
    }

    private function descuentoValido(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($this->total() < 0) {
                $fail('El descuento no puede ser mayor que el subtotal.');
            }
        };
    }

    private function pagosCubrenElTotal(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $pagado = round(collect((array) $value)->sum(fn ($p) => (float) ($p['monto'] ?? 0)), 2);
            $total = $this->total();

            if (abs($pagado - $total) >= 0.005) {
                $fail(sprintf('Los pagos (S/ %s) tienen que sumar el total (S/ %s).', number_format($pagado, 2), number_format($total, 2)));
            }
        };
    }

    private function productoActivo(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $activo = Variante::query()->whereKey($value)
                ->whereHas('producto', fn ($q) => $q->where('activo', true))
                ->exists();

            if (! $activo) {
                $fail('El producto de esta presentación está desactivado.');
            }
        };
    }

    private function varianteUnica(int|string $indice): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($indice) {
            foreach ((array) $this->input('venta.items', []) as $i => $otro) {
                if ($i === $indice) {
                    return;
                }
                if (($otro['variante_id'] ?? null) == $value) {
                    $fail('Esta presentación ya está en el carrito: sumá la cantidad ahí.');

                    return;
                }
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'venta.items.required' => 'El carrito está vacío.',
            'venta.items.min' => 'El carrito está vacío.',
            'venta.pagos.required' => 'Agregá cómo paga el cliente.',
            'venta.pagos.min' => 'Agregá cómo paga el cliente.',
            'venta.pagos.*.referencia.required' => 'Anotá el número de operación para poder verificar el pago.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'venta.cliente_id' => 'cliente',
            'venta.descuento' => 'descuento',
            'venta.items.*.cantidad' => 'cantidad',
            'venta.items.*.precio_unitario' => 'precio',
            'venta.pagos.*.metodo' => 'método',
            'venta.pagos.*.monto' => 'monto',
            'venta.pagos.*.recibido' => 'recibido',
            'venta.pagos.*.referencia' => 'n° de operación',
        ];
    }
}
