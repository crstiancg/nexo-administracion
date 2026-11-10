<?php

namespace App\Http\Requests;

use App\Models\EstadoOrden;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea y edita un estado de la orden. Payload anidado en `estado`.
 *
 * Inicial y Entregado son del sistema: se renombran y cambian de ícono,
 * color o mensaje, pero no cambian de tipo ni se desactivan. Los que se
 * crean son de los otros tipos.
 */
class StoreEstadoOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $estado = $this->input('estado');
        if (! is_array($estado)) {
            return;
        }

        foreach (['nombre', 'icono', 'mensaje_cliente'] as $campo) {
            if (is_string($estado[$campo] ?? null)) {
                $estado[$campo] = trim($estado[$campo]) ?: null;
            }
        }

        $this->merge(['estado' => $estado]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $actual = $this->actual();
        $deSistema = $actual?->esDeSistema() ?? false;

        return [
            'estado.nombre' => ['required', 'string', 'max:40', $this->nombreUnico($actual)],
            // El de sistema conserva su tipo; uno nuevo no puede ser de sistema.
            'estado.tipo' => $deSistema
                ? ['required', Rule::in([$actual->tipo])]
                : ['required', Rule::in(array_diff(array_keys(EstadoOrden::TIPOS), EstadoOrden::TIPOS_SISTEMA))],
            'estado.icono' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
            'estado.color' => ['required', Rule::in(EstadoOrden::COLORES)],
            'estado.mensaje_cliente' => ['nullable', 'string', 'max:250'],
            'estado.notificar' => ['sometimes', 'boolean'],
            'estado.activo' => $deSistema ? ['required', 'accepted'] : ['required', 'boolean'],
        ];
    }

    private function actual(): ?EstadoOrden
    {
        $estado = $this->route('estado');

        return $estado instanceof EstadoOrden ? $estado : null;
    }

    /** "Revisión" y "REVISIÓN" son el mismo estado. */
    private function nombreUnico(?EstadoOrden $ignorar): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignorar) {
            $existe = EstadoOrden::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $value)])
                ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar->getKey()))
                ->exists();

            if ($existe) {
                $fail('Ya existe un estado con ese nombre.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.tipo.in' => $this->actual()?->esDeSistema()
                ? 'Este estado es del sistema: no cambia de tipo.'
                : 'Elige En proceso, Listo para entregar o Cerrado sin entregar.',
            'estado.activo.accepted' => 'Este estado es del sistema: no se puede desactivar.',
            'estado.icono.regex' => 'Elige un ícono de la lista.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'estado.nombre' => 'nombre',
            'estado.tipo' => 'tipo',
            'estado.icono' => 'ícono',
            'estado.color' => 'color',
            'estado.mensaje_cliente' => 'mensaje para el cliente',
            'estado.notificar' => 'avisar por WhatsApp',
            'estado.activo' => 'activo',
        ];
    }
}
