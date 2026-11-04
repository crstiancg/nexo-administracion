<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cotizacion;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\Stock;
use App\Models\Unidad;
use App\Models\Variante;
use App\Support\Fechas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class CotizacionValidezTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Variante $disco;

    protected function setUp(): void
    {
        parent::setUp();

        $sede = Sede::create(['nombre' => 'Matriz']);
        $producto = Producto::create([
            'nombre' => 'Disco SSD 480 GB',
            'categoria_id' => Categoria::create(['nombre' => 'Almacenamiento'])->id,
            'marca_id' => Marca::create(['nombre' => 'Kingston', 'activo' => true])->id,
            'precio' => 150,
        ]);
        $this->disco = $producto->variantes()->create([
            'presentacion' => 'Unidad',
            'unidad_id' => Unidad::create(['nombre' => 'Unidad', 'abreviatura' => 'und'])->id,
            'sku' => 'SSD-480',
        ]);
        (new Stock)->forceFill(['sede_id' => $sede->id, 'variante_id' => $this->disco->id, 'cantidad' => 0, 'activo' => true])->save();

        $this->actuarCon('cotizaciones.store')->forceFill(['sede_id' => $sede->id])->save();
    }

    private function crear(array $datos): TestResponse
    {
        return $this->postJson('/api/cotizaciones', ['cotizacion' => $datos + [
            'items' => [['variante_id' => $this->disco->id, 'cantidad' => 1, 'precio_unitario' => 150]],
        ]]);
    }

    public function test_sin_validez_vale_siete_dias(): void
    {
        $this->crear(['valida_hasta' => ''])->assertCreated();

        $this->assertSame(
            Fechas::hoy()->addDays(7)->toDateString(),
            Cotizacion::sole()->valida_hasta->toDateString(),
        );
    }

    public function test_respeta_la_validez_que_se_pone(): void
    {
        $fecha = Fechas::hoy()->addDays(20)->toDateString();

        $this->crear(['valida_hasta' => $fecha])->assertCreated();

        $this->assertSame($fecha, Cotizacion::sole()->valida_hasta->toDateString());
    }
}
