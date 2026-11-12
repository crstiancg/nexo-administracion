<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Unidad;
use App\Models\Variante;
use App\Support\Ean13;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * Las presentaciones sin código de barras de fábrica reciben uno interno
 * (EAN-13 con prefijo de uso interno) al guardar el producto.
 */
class CodigoBarrasInternoTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function producto(array $variantes): array
    {
        $unidad = Unidad::create(['nombre' => 'Unidad', 'abreviatura' => 'und'])->id;

        return ['producto' => [
            'nombre' => 'Cable HDMI',
            'categoria_id' => Categoria::create(['nombre' => 'Cables'])->id,
            'marca_id' => Marca::create(['nombre' => 'Genérico', 'activo' => true])->id,
            'precio' => '25.00',
            'activo' => true,
            'maneja_lotes' => false,
            'variantes' => array_map(fn ($v) => [...$v, 'unidad_id' => $unidad], $variantes),
        ]];
    }

    public function test_sin_codigo_el_sistema_genera_uno_interno_y_respeta_el_de_fabrica(): void
    {
        $this->actuarCon('productos.store');

        $this->postJson('/api/productos', $this->producto([
            ['presentacion' => '1.8 m', 'sku' => 'HDMI-18', 'codigo_barras' => ''],
            ['presentacion' => '3 m', 'sku' => 'HDMI-3', 'codigo_barras' => '7751234567890'],
        ]))->assertCreated();

        $corto = Variante::query()->where('sku', 'HDMI-18')->first();
        $this->assertSame(Ean13::paraVariante($corto->id), $corto->codigo_barras);
        $this->assertTrue(Ean13::esValido($corto->codigo_barras));
        $this->assertSame('7751234567890', Variante::query()->where('sku', 'HDMI-3')->value('codigo_barras'));
    }

    public function test_si_el_codigo_interno_ya_lo_tiene_otro_usa_el_siguiente_prefijo(): void
    {
        $this->actuarCon('productos.store');

        // Alguien registró a mano el código que le tocaría a la variante 2.
        $this->postJson('/api/productos', $this->producto([
            ['presentacion' => 'Única', 'sku' => 'OTRO-1', 'codigo_barras' => Ean13::paraVariante(2)],
            ['presentacion' => 'Doble', 'sku' => 'OTRO-2'],
        ]))->assertCreated();

        $segunda = Variante::query()->where('sku', 'OTRO-2')->first();
        $this->assertSame(2, $segunda->id);
        $this->assertSame(Ean13::paraVariante(2, '21'), $segunda->codigo_barras);
    }
}
