<?php

namespace Tests\Feature;

use App\Http\Controllers\ProformaController;
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
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * El enlace de la proforma que se manda por WhatsApp: se abre sin sesión y
 * sólo muestra lo que sale impreso.
 */
class ProformaPublicaTest extends TestCase
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
        (new Stock)->forceFill(['sede_id' => $sede->id, 'variante_id' => $this->disco->id, 'cantidad' => 5, 'activo' => true])->save();

        $this->actuarCon('cotizaciones.store')->forceFill(['sede_id' => $sede->id])->save();
    }

    private function crear(): Cotizacion
    {
        $id = $this->postJson('/api/cotizaciones', ['cotizacion' => [
            'valida_hasta' => Fechas::hoy()->addDays(7)->toDateString(),
            'observacion' => 'Nota interna: le hicimos precio especial',
            'items' => [['variante_id' => $this->disco->id, 'cantidad' => 2, 'precio_unitario' => 150]],
        ]])->assertCreated()->assertJsonStructure(['token'])->json('id');

        return Cotizacion::findOrFail($id);
    }

    public function test_cada_cotizacion_nueva_tiene_su_token(): void
    {
        $a = $this->crear();
        $b = $this->crear();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $a->token);
        $this->assertNotSame($a->token, $b->token);
    }

    public function test_la_proforma_se_ve_sin_sesion_y_sin_datos_internos(): void
    {
        $cotizacion = $this->crear();
        $this->app['auth']->forgetGuards();

        $respuesta = $this->getJson("/api/proforma/{$cotizacion->token}")
            ->assertOk()
            ->assertJsonPath('cotizacion.codigo', $cotizacion->codigo)
            ->assertJsonPath('cotizacion.estado', 'pendiente')
            ->assertJsonPath('cotizacion.total', '300.00')
            ->assertJsonPath('cotizacion.items.0.variante.sku', 'SSD-480')
            ->assertJsonPath('cotizacion.items.0.variante.producto.nombre', 'Disco SSD 480 GB')
            ->assertJsonStructure(['empresa' => ['nombre', 'ruc']]);

        $json = $respuesta->getContent();
        $this->assertStringNotContainsString('Nota interna', $json);
        $this->assertStringNotContainsString('costo_promedio', $json);
        $this->assertStringNotContainsString('stock', $json);
        $this->assertStringNotContainsString('token', $json);
    }

    public function test_cuenta_las_visitas_del_cliente_sin_las_recargas_ni_las_del_personal(): void
    {
        $cotizacion = $this->crear();

        // El vendedor la mira con su sesión: no cuenta.
        $this->getJson("/api/proforma/{$cotizacion->token}")->assertOk();
        $this->assertSame(0, $cotizacion->fresh()->vistas);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/proforma/{$cotizacion->token}")->assertOk();
        $this->assertSame(1, $cotizacion->fresh()->vistas);

        // Recarga enseguida: misma visita.
        $this->getJson("/api/proforma/{$cotizacion->token}")->assertOk();
        $this->assertSame(1, $cotizacion->fresh()->vistas);

        $this->travel(ProformaController::MINUTOS_ENTRE_VISTAS + 1)->minutes();
        $this->getJson("/api/proforma/{$cotizacion->token}")->assertOk();
        $this->assertSame(2, $cotizacion->fresh()->vistas);
        $this->assertNotNull($cotizacion->fresh()->vista_at);

        $this->actuarCon('cotizaciones.index');
        $this->getJson('/api/cotizaciones?vista=si')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.vistas', 2);
        $this->getJson('/api/cotizaciones?vista=no')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_un_token_que_no_existe_da_404(): void
    {
        $this->getJson('/api/proforma/'.str_repeat('x', 32))->assertNotFound();
    }

    public function test_el_mensaje_de_whatsapp_de_la_cotizacion_se_puede_cambiar(): void
    {
        $this->actuarCon('configuracion.update');

        $this->putJson('/api/configuracion', [
            'empresa' => ['nombre' => 'NEXO TECHNOLOGY S.R.L.'],
            'whatsapp' => ['cotizacion' => 'Hola {cliente}, tu proforma: {enlace}'],
        ])->assertOk()->assertJsonPath('whatsapp.cotizacion', 'Hola {cliente}, tu proforma: {enlace}');
    }
}
