<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Crea y actualiza categorías. Payload anidado en `categoria`.
 */
class StoreCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $categoria = $this->input('categoria');
        if (! is_array($categoria)) {
            return;
        }

        if (is_string($categoria['nombre'] ?? null)) {
            $categoria['nombre'] = trim($categoria['nombre']);
        }

        // El select vacío puede llegar como "" en vez de null.
        if (($categoria['parent_id'] ?? null) === '') {
            $categoria['parent_id'] = null;
        }

        $this->merge(['categoria' => $categoria]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categoria = $this->route('categoria');

        return [
            'categoria.nombre' => ['required', 'string', 'max:60', $this->nombreUnicoEntreHermanos($categoria)],
            'categoria.parent_id' => [
                'nullable', 'integer', 'exists:categorias,id',
                $this->sinCiclos($categoria),
            ],
        ];
    }

    /**
     * "Polos" y "polos" bajo el mismo padre son la misma categoría. MySQL ya
     * compara sin distinguir mayúsculas, SQLite no; y el unique de la tabla
     * no cubre las raíces (NULL ≠ NULL). Se chequea explícito.
     */
    private function nombreUnicoEntreHermanos(?Categoria $ignorar): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignorar) {
            $padre = $this->input('categoria.parent_id');

            $existe = Categoria::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $value)])
                ->when($padre, fn ($q) => $q->where('parent_id', $padre), fn ($q) => $q->whereNull('parent_id'))
                ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar->getKey()))
                ->exists();

            if ($existe) {
                $fail($padre
                    ? 'Ya existe una subcategoría con ese nombre en esa categoría.'
                    : 'Ya existe una categoría principal con ese nombre.');
            }
        };
    }

    /**
     * Al editar, el padre no puede ser la misma categoría ni una de sus
     * subcategorías: el árbol quedaría en un ciclo sin raíz.
     */
    private function sinCiclos(?Categoria $categoria): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($categoria) {
            if (! $categoria || $value === null) {
                return;
            }

            $prohibidos = [$categoria->getKey(), ...$categoria->descendientesIds()];

            if (in_array((int) $value, $prohibidos, true)) {
                $fail('La categoría padre no puede ser la misma categoría ni una de sus subcategorías.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'categoria.nombre' => 'nombre',
            'categoria.parent_id' => 'categoría padre',
        ];
    }
}
