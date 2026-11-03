<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Unidad;
use Illuminate\Database\Seeder;

/**
 * Catálogo base de una tienda de tecnología con servicio técnico: unidades de
 * venta, las marcas más comunes (sirven para los productos y para los equipos
 * de las órdenes de servicio) y las categorías. Se puede correr de nuevo: no
 * duplica.
 */
class CatalogoSeeder extends Seeder
{
    public const MARCAS = [
        'Genérico', 'Acer', 'Apple', 'Asus', 'Brother', 'Canon', 'Dell', 'Epson', 'Genius', 'HP',
        'Huawei', 'Kingston', 'Lenovo', 'LG', 'Logitech', 'Motorola', 'Samsung', 'Sony', 'Toshiba',
        'TP-Link', 'Xiaomi',
    ];

    public function run(): void
    {
        // [nombre, abreviatura, fraccionable, código SUNAT (catálogo 03)]
        $unidades = [
            ['Unidad', 'UND', false, 'NIU'],
            ['Caja', 'CJ', false, 'BX'],
            ['Paquete', 'PQT', false, 'PK'],
            ['Kit', 'KIT', false, 'KT'],
            ['Rollo', 'RLL', false, 'RO'],
            ['Metro', 'M', true, 'MTR'],
        ];
        foreach ($unidades as [$nombre, $abreviatura, $fraccionable, $sunat]) {
            Unidad::firstOrCreate(
                ['nombre' => $nombre],
                ['abreviatura' => $abreviatura, 'fraccionable' => $fraccionable, 'codigo_sunat' => $sunat],
            );
        }

        foreach (self::MARCAS as $marca) {
            Marca::firstOrCreate(['nombre' => $marca], ['activo' => true]);
        }

        $categorias = [
            'Accesorios' => ['Mouse y teclados', 'Audífonos y parlantes', 'Cables y adaptadores', 'Cargadores'],
            'Almacenamiento' => ['Discos SSD', 'Memorias USB y microSD'],
            'Componentes' => ['Memorias RAM', 'Fuentes de poder', 'Pasta térmica y refrigeración'],
            'Repuestos' => ['Pantallas', 'Baterías', 'Teclados de laptop', 'Pines de carga'],
            'Impresión' => ['Tintas', 'Tóner'],
            'Redes' => [],
            'Equipos' => ['Laptops', 'Monitores', 'Impresoras'],
            'Software y licencias' => [],
        ];
        foreach ($categorias as $raiz => $hijas) {
            $padre = Categoria::firstOrCreate(['parent_id' => null, 'nombre' => $raiz]);
            foreach ($hijas as $hija) {
                Categoria::firstOrCreate(['parent_id' => $padre->id, 'nombre' => $hija]);
            }
        }
    }
}
