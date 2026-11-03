<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Unidad;
use Illuminate\Database\Seeder;

/**
 * Catálogo base de una tienda de materiales de construcción: unidades de
 * venta, la marca principal y las categorías (como las de un distribuidor
 * Sika). Se puede correr de nuevo: no duplica.
 */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        // [nombre, abreviatura, fraccionable]
        $unidades = [
            ['Unidad', 'UND', false],
            ['Bolsa', 'BLS', false],
            ['Saco', 'SCO', false],
            ['Balde', 'BLD', false],
            ['Galón', 'GL', false],
            ['Litro', 'L', true],
            ['Cilindro', 'CIL', false],
            ['Cartucho', 'CRT', false],
            ['Lata', 'LT', false],
            ['Caja', 'CJ', false],
            ['Rollo', 'RLL', false],
            ['Kit', 'KIT', false],
            ['Kilogramo', 'KG', true],
            ['Metro', 'M', true],
            ['Metro cuadrado', 'M2', true],
        ];
        foreach ($unidades as [$nombre, $abreviatura, $fraccionable]) {
            Unidad::firstOrCreate(['nombre' => $nombre], ['abreviatura' => $abreviatura, 'fraccionable' => $fraccionable]);
        }

        Marca::firstOrCreate(['nombre' => 'Sika'], ['activo' => true]);

        $categorias = [
            'Sellantes y Adhesivos' => ['Acrílicos e imprimantes', 'Poliuretanos', 'Siliconas'],
            'Grouting y Anclajes' => ['Grouting y nivelación de maquinarias', 'Anclajes'],
            'Impermeabilizantes' => ['Techos y cubiertas', 'Para mezclas de concreto', 'Estructuras de concreto'],
            'Pegamentos para Cerámicos' => [],
            'Reparación y Reforzamiento' => ['Morteros de reparación', 'Puentes de adherencia', 'Inyección estructural'],
            'Aditivos para Concreto' => [],
        ];
        foreach ($categorias as $raiz => $hijas) {
            $padre = Categoria::firstOrCreate(['parent_id' => null, 'nombre' => $raiz]);
            foreach ($hijas as $hija) {
                Categoria::firstOrCreate(['parent_id' => $padre->id, 'nombre' => $hija]);
            }
        }
    }
}
