<?php

namespace Tests\Feature;

use App\Models\Archivo;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * Lo que la tienda no tiene se cotiza igual (ítem libre, descrito a mano) y
 * cualquier ítem puede llevar una foto que sale en la proforma.
 */
class CotizacionItemLibreTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Variante $disco;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

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
        (new Stock)->forceFill(['sede_id' => $sede->id, 'variante_id' => $this->disco->id, 'cantidad' => 5, 'activo' => true])->save();

        $this->actuarCon('cotizaciones.store', 'cotizaciones.update', 'cotizaciones.convertir')
            ->forceFill(['sede_id' => $sede->id])->save();
    }

    private function crear(array $items): TestResponse
    {
        // post() y no postJson(): con fotos viaja en multipart, como desde el form.
        return $this->post('/api/cotizaciones', ['cotizacion' => [
            'valida_hasta' => Fechas::hoy()->addDays(7)->toDateString(),
            'items' => $items,
        ]], ['Accept' => 'application/json']);
    }

    private function libre(array $datos = []): array
    {
        return $datos + ['descripcion' => 'Impresora Epson L3250', 'unidad' => 'und', 'cantidad' => 2, 'precio_unitario' => 700];
    }

    public function test_cotiza_lo_que_no_hay_en_tienda_junto_a_lo_del_catalogo(): void
    {
        $this->crear([
            ['variante_id' => $this->disco->id, 'cantidad' => 1, 'precio_unitario' => 150],
            $this->libre(),
        ])->assertCreated()
            ->assertJsonPath('items.1.variante', null)
            ->assertJsonPath('items.1.descripcion', 'Impresora Epson L3250')
            ->assertJsonPath('items.1.unidad', 'und')
            ->assertJsonPath('total', '1550.00');
    }

    public function test_un_item_sin_producto_ni_descripcion_no_pasa(): void
    {
        $this->crear([['descripcion' => '  ', 'cantidad' => 1, 'precio_unitario' => 10]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cotizacion.items.0.variante_id' => 'Elige un producto o describe lo que ofreces.']);
    }

    public function test_la_foto_del_item_va_en_archivos(): void
    {
        $respuesta = $this->crear([$this->libre(['archivos' => [['archivo' => UploadedFile::fake()->image('impresora.jpg', 800, 600)]]])])
            ->assertCreated()
            ->assertJsonCount(1, 'items.0.archivos');

        $item = Cotizacion::sole()->items()->sole();
        $foto = $item->archivos()->sole();
        Storage::disk('public')->assertExists($foto->ruta);
        $this->assertSame($foto->id, $respuesta->json('items.0.archivos.0.id'));
    }

    public function test_al_editar_conserva_la_foto_sin_copiarla(): void
    {
        $id = $this->crear([$this->libre(['archivos' => [['archivo' => UploadedFile::fake()->image('impresora.jpg')]]])])->json('id');
        $foto = Cotizacion::find($id)->items()->sole()->archivos()->sole();

        $this->editar($id, [$this->libre(['precio_unitario' => 650, 'archivos' => [['id' => $foto->id]]])])
            ->assertOk()
            ->assertJsonPath('items.0.archivos.0.id', $foto->id);

        $this->assertSame(1, Archivo::count());
        Storage::disk('public')->assertExists($foto->ruta);
    }

    public function test_al_quitar_la_foto_se_borra(): void
    {
        $id = $this->crear([$this->libre(['archivos' => [['archivo' => UploadedFile::fake()->image('impresora.jpg')]]])])->json('id');
        $foto = Cotizacion::find($id)->items()->sole()->archivos()->sole();

        $this->editar($id, [$this->libre()])->assertOk();

        $this->assertSame(0, Archivo::count());
        Storage::disk('public')->assertMissing($foto->ruta);
    }

    public function test_no_acepta_fotos_de_otra_cotizacion(): void
    {
        $otra = $this->crear([$this->libre(['archivos' => [['archivo' => UploadedFile::fake()->image('a.jpg')]]])])->json('id');
        $ajena = Cotizacion::find($otra)->items()->sole()->archivos()->sole();
        $id = $this->crear([$this->libre()])->json('id');

        $this->editar($id, [$this->libre(['archivos' => [['id' => $ajena->id]]])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cotizacion.items.0.archivos.0.id');
    }

    private function editar(int $id, array $items): TestResponse
    {
        return $this->post("/api/cotizaciones/{$id}", ['_method' => 'PUT', 'cotizacion' => [
            'valida_hasta' => Fechas::hoy()->addDays(7)->toDateString(),
            'items' => $items,
        ]], ['Accept' => 'application/json']);
    }

    public function test_al_convertir_los_libres_no_pasan_al_pedido(): void
    {
        $id = $this->crear([
            ['variante_id' => $this->disco->id, 'cantidad' => 1, 'precio_unitario' => 150],
            $this->libre(),
        ])->json('id');

        $this->postJson("/api/cotizaciones/{$id}/convertir")->assertOk();

        $pedido = Cotizacion::find($id)->pedido;
        $this->assertCount(1, $pedido->items);
        $this->assertSame($this->disco->id, $pedido->items->first()->variante_id);
    }

    public function test_solo_libres_no_se_convierte(): void
    {
        $id = $this->crear([$this->libre()])->json('id');

        $this->postJson("/api/cotizaciones/{$id}/convertir")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_pc_armada_guarda_los_componentes_y_puede_ocultar_precios(): void
    {
        $this->post('/api/cotizaciones', ['cotizacion' => [
            'tipo' => 'pc',
            'mostrar_precios' => 'false',
            'valida_hasta' => Fechas::hoy()->addDays(7)->toDateString(),
            'items' => [
                ['componente' => 'Procesador', 'descripcion' => 'Intel Core i5-14400', 'cantidad' => 1, 'precio_unitario' => 950],
                ['componente' => 'Disco sólido SSD', 'variante_id' => $this->disco->id, 'cantidad' => 1, 'precio_unitario' => 150],
            ],
        ]], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('tipo', 'pc')
            ->assertJsonPath('mostrar_precios', false)
            ->assertJsonPath('items.0.componente', 'Procesador')
            ->assertJsonPath('items.1.componente', 'Disco sólido SSD')
            ->assertJsonPath('total', '1100.00');
    }

    public function test_en_una_normal_no_guarda_componentes(): void
    {
        $this->crear([$this->libre(['componente' => 'Procesador'])])
            ->assertCreated()
            ->assertJsonPath('tipo', 'normal')
            ->assertJsonPath('items.0.componente', null);
    }

    public function test_la_lista_filtra_por_tipo_fecha_y_quien_la_hizo(): void
    {
        $this->crear([$this->libre()])->assertCreated();
        $this->post('/api/cotizaciones', ['cotizacion' => [
            'tipo' => 'pc',
            'valida_hasta' => Fechas::hoy()->addDays(7)->toDateString(),
            'items' => [['componente' => 'Procesador', 'descripcion' => 'Core i5', 'cantidad' => 1, 'precio_unitario' => 950]],
        ]], ['Accept' => 'application/json'])->assertCreated();
        $yo = auth()->user();
        $yo->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('cotizaciones.index', 'api'));

        $this->getJson('/api/cotizaciones?tipo=pc')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.tipo', 'pc');
        $this->getJson('/api/cotizaciones?user_id='.$yo->id)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/cotizaciones?desde='.Fechas::hoy()->addDay()->toDateString())->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/cotizaciones/usuarios')->assertOk()->assertJsonPath('0.id', $yo->id);
    }

    public function test_filtra_las_por_vencer_y_las_convertidas_en_pedido(): void
    {
        $this->actuarCon('cotizaciones.store', 'cotizaciones.convertir', 'cotizaciones.index')
            ->forceFill(['sede_id' => Stock::first()->sede_id])->save();
        $item = ['variante_id' => $this->disco->id, 'cantidad' => 1, 'precio_unitario' => 150];
        $pronto = $this->crear([$item])->json('id');
        Cotizacion::find($pronto)->forceFill(['valida_hasta' => Fechas::hoy()->addDay()])->save();
        $lejos = $this->post('/api/cotizaciones', ['cotizacion' => [
            'valida_hasta' => Fechas::hoy()->addDays(10)->toDateString(), 'items' => [$item],
        ]], ['Accept' => 'application/json'])->json('id');
        $this->postJson("/api/cotizaciones/{$lejos}/convertir")->assertOk();

        $this->getJson('/api/cotizaciones?estado=por_vencer')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pronto);
        $this->getJson('/api/cotizaciones?convertida_en=pedido')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $lejos);
        $this->getJson('/api/cotizaciones?convertida_en=os')->assertOk()->assertJsonCount(0, 'data');
    }
}
