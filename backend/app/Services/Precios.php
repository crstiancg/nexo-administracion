<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Oferta;
use App\Models\Producto;
use App\Models\Variante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El precio de venta vigente: el de lista (el de la variante o el base del
 * producto) con la mejor oferta que le toque. Una variante puede caer en
 * varias ofertas (la de su producto, una suya puntual, la de su categoría):
 * gana la que deja el precio MÁS BAJO; nunca se suman.
 *
 * Registrado como `scoped` (una instancia por request): las ofertas vigentes
 * y sus destinos se cargan una sola vez y se cruzan en memoria, sin una
 * consulta por producto en el catálogo.
 */
class Precios
{
    /** @var Collection<int, Oferta>|null por id */
    private ?Collection $vigentes = null;

    /** @var array<string, array<int, int[]>> tipo → id del destino → ids de ofertas */
    private array $destinos = ['producto' => [], 'variante' => [], 'categoria' => []];

    /**
     * @return array{precio: string, precio_lista: string, oferta: ?Oferta}
     */
    public function vigente(float $precioLista, int $productoId, ?int $categoriaId, ?int $varianteId = null): array
    {
        $mejor = null;
        $mejorPrecio = $precioLista;

        foreach ($this->ofertasPara($productoId, $categoriaId, $varianteId) as $oferta) {
            $precio = $oferta->aplicarA($precioLista);
            // Una oferta que no baja el precio (un fijo mayor al de lista) no
            // cuenta.
            if ($precio < $mejorPrecio) {
                $mejor = $oferta;
                $mejorPrecio = $precio;
            }
        }

        return [
            'precio' => number_format($mejorPrecio, 2, '.', ''),
            'precio_lista' => number_format($precioLista, 2, '.', ''),
            'oferta' => $mejor,
        ];
    }

    /**
     * @return Collection<int, Oferta>
     */
    private function ofertasPara(int $productoId, ?int $categoriaId, ?int $varianteId): Collection
    {
        $this->cargar();

        $ids = array_unique([
            ...($this->destinos['producto'][$productoId] ?? []),
            ...($varianteId !== null ? ($this->destinos['variante'][$varianteId] ?? []) : []),
            ...($categoriaId !== null ? ($this->destinos['categoria'][$categoriaId] ?? []) : []),
        ]);

        return $this->vigentes->only($ids);
    }

    private function cargar(): void
    {
        if ($this->vigentes !== null) {
            return;
        }

        $this->vigentes = Oferta::query()->vigentes()->get()->keyBy('id');
        if ($this->vigentes->isEmpty()) {
            return;
        }

        $tipos = [Producto::class => 'producto', Variante::class => 'variante', Categoria::class => 'categoria'];

        $filas = DB::table('ofertables')
            ->whereIn('oferta_id', $this->vigentes->keys())
            ->get(['oferta_id', 'ofertable_type', 'ofertable_id']);

        foreach ($filas as $fila) {
            $tipo = $tipos[$fila->ofertable_type] ?? null;
            if (! $tipo) {
                continue;
            }

            // Una categoría alcanza a sus subcategorías (a cualquier
            // profundidad) si la oferta lo pide.
            $ids = [$fila->ofertable_id];
            if ($tipo === 'categoria' && $this->vigentes[$fila->oferta_id]->incluye_subcategorias) {
                $ids = [...$ids, ...(Categoria::find($fila->ofertable_id)?->descendientesIds() ?? [])];
            }

            foreach ($ids as $id) {
                $this->destinos[$tipo][$id][] = $fila->oferta_id;
            }
        }
    }
}
