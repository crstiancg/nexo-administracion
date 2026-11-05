<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cotizacion;
use App\Models\Marca;
use App\Models\PlantillaPc;
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

/**
 * PC armada: cotizar N equipos iguales y guardar/cargar plantillas.
 */
class CotizacionPcTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Variante $disco;

    private Sede $sede;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sede = Sede::create(['nombre' => 'Matriz']);
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
        (new Stock)->forceFill(['sede_id' => $this->sede->id, 'variante_id' => $this->disco->id, 'cantidad' => 50, 'activo' => true])->save();

        $this->actuarCon('cotizaciones.store', 'cotizaciones.convertir')
            ->forceFill(['sede_id' => $this->sede->id])->save();
    }

    private function crear(array $cotizacion): TestResponse
    {
        return $this->postJson('/api/cotizaciones', ['cotizacion' => $cotizacion + [
            'tipo' => 'pc',
            'valida_hasta' => Fechas::hoy()->addDays(7)->toDateString(),
            'items' => [
                ['componente' => 'Disco sólido SSD', 'variante_id' => $this->disco->id, 'cantidad' => 1, 'precio_unitario' => 150],
                ['componente' => 'Procesador', 'descripcion' => 'Intel Core i5-14400', 'cantidad' => 1, 'precio_unitario' => 850],
            ],
        ]]);
    }

    public function test_una_pc_por_varios_equipos_multiplica_el_total(): void
    {
        $this->crear(['equipos' => 10, 'descuento' => 500])
            ->assertCreated()
            ->assertJsonPath('equipos', 10)
            // Los ítems son los de UN equipo.
            ->assertJsonPath('items.0.cantidad', 1)
            ->assertJsonPath('subtotal', '10000.00')
            ->assertJsonPath('total', '9500.00');
    }

    public function test_el_descuento_se_compara_con_el_subtotal_de_todos_los_equipos(): void
    {
        $this->crear(['equipos' => 2, 'descuento' => 1500])->assertCreated();
        $this->crear(['equipos' => 2, 'descuento' => 2500])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cotizacion.descuento');
    }

    public function test_una_cotizacion_normal_siempre_es_por_un_equipo(): void
    {
        $this->crear(['tipo' => 'normal', 'equipos' => 5])
            ->assertCreated()
            ->assertJsonPath('equipos', 1)
            ->assertJsonPath('total', '1000.00');
    }

    public function test_al_convertir_pasan_las_cantidades_de_todos_los_equipos(): void
    {
        $id = $this->crear(['equipos' => 4])->json('id');

        $this->postJson("/api/cotizaciones/{$id}/convertir")->assertOk();

        $pedido = Cotizacion::find($id)->pedido;
        $this->assertEquals(4, $pedido->items()->sole()->cantidad);

        // Elegir menos: el tope es lo cotizado para los 4 equipos.
        $otra = $this->crear(['equipos' => 4])->json('id');
        $this->postJson("/api/cotizaciones/{$otra}/convertir", ['items' => [['variante_id' => $this->disco->id, 'cantidad' => 5]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.cantidad');
    }

    public function test_guarda_una_plantilla_y_al_cargarla_trae_el_precio_de_hoy(): void
    {
        $this->postJson('/api/cotizaciones/plantillas', [
            'nombre' => ' PC Oficina i5 ',
            'garantia' => '1 año',
            'items' => [
                ['componente' => 'Disco sólido SSD', 'variante_id' => $this->disco->id, 'descripcion' => 'se ignora', 'cantidad' => 1, 'precio_unitario' => 99],
                ['componente' => 'Procesador', 'descripcion' => 'Intel Core i5-14400', 'unidad' => 'und', 'cantidad' => 1, 'precio_unitario' => 850],
            ],
        ])->assertCreated();

        $this->disco->producto->forceFill(['precio' => 170])->save();

        $this->getJson('/api/cotizaciones/plantillas')
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'PC Oficina i5')
            ->assertJsonPath('data.0.garantia', '1 año')
            ->assertJsonPath('data.0.items.0.variante.sku', 'SSD-480')
            ->assertJsonPath('data.0.items.0.variante.precio', '170.00')
            ->assertJsonPath('data.0.items.0.precio_unitario', null)
            ->assertJsonPath('data.0.items.0.descripcion', '')
            ->assertJsonPath('data.0.items.1.variante', null)
            ->assertJsonPath('data.0.items.1.descripcion', 'Intel Core i5-14400')
            ->assertJsonPath('data.0.items.1.precio_unitario', 850);
    }

    public function test_el_mismo_nombre_reemplaza_la_plantilla(): void
    {
        $plantilla = fn (float $precio) => [
            'nombre' => 'PC Gamer',
            'items' => [['componente' => 'Procesador', 'descripcion' => 'Ryzen 7', 'cantidad' => 1, 'precio_unitario' => $precio]],
        ];

        $this->postJson('/api/cotizaciones/plantillas', $plantilla(1000))->assertCreated();
        $this->postJson('/api/cotizaciones/plantillas', $plantilla(1100))->assertOk();

        $this->assertSame(1, PlantillaPc::count());
        $this->assertEquals(1100, PlantillaPc::sole()->items[0]['precio_unitario']);
    }

    public function test_un_producto_que_ya_no_se_vende_vuelve_como_casillero_vacio(): void
    {
        PlantillaPc::create([
            'nombre' => 'PC vieja',
            'items' => [['componente' => 'Disco sólido SSD', 'variante_id' => $this->disco->id, 'descripcion' => null, 'unidad' => null, 'cantidad' => 1, 'precio_unitario' => null]],
        ]);
        $this->disco->producto->forceFill(['activo' => false])->save();

        $this->getJson('/api/cotizaciones/plantillas')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.variante', null)
            ->assertJsonPath('data.0.items.0.componente', 'Disco sólido SSD')
            ->assertJsonPath('data.0.items.0.no_disponible', true);
    }

    public function test_borra_una_plantilla(): void
    {
        $plantilla = PlantillaPc::create(['nombre' => 'PC Oficina', 'items' => []]);

        $this->deleteJson("/api/cotizaciones/plantillas/{$plantilla->id}")->assertNoContent();
        $this->assertSame(0, PlantillaPc::count());
    }

    public function test_sin_permiso_de_cotizar_no_ve_las_plantillas(): void
    {
        $this->actuarCon('cotizaciones.index');

        $this->getJson('/api/cotizaciones/plantillas')->assertForbidden();
    }
}
