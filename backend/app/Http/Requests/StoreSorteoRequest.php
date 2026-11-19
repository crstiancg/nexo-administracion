<?php

namespace App\Http\Requests;

use App\Models\Sorteo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y edita un sorteo (sólo mientras está abierto). Payload anidado en
 * `sorteo`: datos, `sedes` ([ids]; vacío = todas) y `premios`
 * ([{descripcion}], en orden: el primero es el 1.° premio).
 *
 * Cambiar el monto por ticket no toca los tickets ya emitidos: vale para
 * las ventas que vienen.
 */
class StoreSorteoRequest extends FormRequest
{
    public const MAX_PREMIOS = 50;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sorteo = $this->input('sorteo');
        if (! is_array($sorteo)) {
            return;
        }

        foreach (['nombre', 'bases'] as $campo) {
            if (is_string($sorteo[$campo] ?? null)) {
                $sorteo[$campo] = trim($sorteo[$campo]) ?: null;
            }
        }
        if (in_array($sorteo['precio_ticket'] ?? null, ['', 0, '0'], true)) {
            $sorteo['precio_ticket'] = null;
        }
        $sorteo['un_premio_por_cliente'] ??= true;
        $sorteo['sedes'] ??= [];
        // Los premios sin descripción (filas vacías) no viajan.
        $sorteo['premios'] = array_values(array_filter(
            array_map(fn ($p) => is_array($p) ? ['descripcion' => trim((string) ($p['descripcion'] ?? ''))] : $p, (array) ($sorteo['premios'] ?? [])),
            fn ($p) => ! is_array($p) || $p['descripcion'] !== '',
        ));

        $this->merge(['sorteo' => $sorteo]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sorteo.nombre' => ['required', 'string', 'max:100'],
            'sorteo.bases' => ['nullable', 'string', 'max:3000'],
            'sorteo.periodo' => ['required', Rule::in(Sorteo::PERIODOS)],
            'sorteo.inicia_el' => ['required', 'date'],
            'sorteo.termina_el' => ['required', 'date', 'after_or_equal:sorteo.inicia_el'],
            // Sólo el sugerido al vender tickets: en cada venta se elige el precio.
            'sorteo.precio_ticket' => ['nullable', 'numeric', 'min:0.1', 'max:99999.99', 'decimal:0,2'],
            'sorteo.un_premio_por_cliente' => ['boolean'],
            'sorteo.sedes' => ['array'],
            'sorteo.sedes.*' => ['integer', 'distinct', 'exists:sedes,id'],
            'sorteo.premios' => ['required', 'array', 'min:1', 'max:'.self::MAX_PREMIOS],
            'sorteo.premios.*.descripcion' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sorteo.premios.required' => 'Agrega al menos un premio.',
            'sorteo.premios.min' => 'Agrega al menos un premio.',
            'sorteo.termina_el.after_or_equal' => 'Termina el mismo día que empieza o después.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sorteo.nombre' => 'nombre',
            'sorteo.bases' => 'bases',
            'sorteo.periodo' => 'período',
            'sorteo.inicia_el' => 'inicio',
            'sorteo.termina_el' => 'cierre',
            'sorteo.precio_ticket' => 'precio sugerido por ticket',
            'sorteo.sedes' => 'sedes',
            'sorteo.sedes.*' => 'sede',
            'sorteo.premios.*.descripcion' => 'premio',
        ];
    }
}
