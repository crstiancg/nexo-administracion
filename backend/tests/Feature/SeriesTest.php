<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\Serie;
use App\Models\Unidad;
use App\Models\Variante;
use App\Services\Inventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * Productos con número de serie: cada unidad entra con su serie, sale la que
 * se entrega y se puede consultar a quién se vendió y su garantía.
 */
class SeriesTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Sede $sede;

    private Variante $laptop;

    private Inventario $inventario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sede = Sede::create(['nombre' => 'Matriz']);
        $producto = Producto::create([
            'nombre' => 'Laptop HP 250 G9',
            'categoria_id' => Categoria::create(['nombre' => 'Laptops'])->id,
            'marca_id' => Marca::create(['nombre' => 'HP', 'activo' => true])->id,
            'precio' => 2500,
            'maneja_series' => true,
            'garantia_meses' => 12,
        ]);
        $this->laptop = $producto->variantes()->create([
            'presentacion' => 'Unidad',
            'unidad_id' => Unidad::create(['nombre' => 'Unidad', 'abreviatura' => 'und'])->id,
            'sku' => 'HP-250',
        ]);
        $this->inventario = app(Inventario::class);
    }

    private function entrar(array $series, ?int $sedeId = null): void
    {
        $this->inventario->entrada($sedeId ?? $this->sede->id, [
            ['variante_id' => $this->laptop->id, 'cantidad' => count($series), 'costo_unitario' => 2000, 'series' => $series],
        ], 'F001-1', null, null, MovimientoInventario::MOTIVO_COMPRA);
    }

    private function errorDe(callable $accion): string
    {
        try {
            $accion();
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->first();
        }
        $this->fail('Se esperaba un error de validación.');
    }

    public function test_cada_unidad_entra_con_su_serie(): void
    {
        $this->entrar([' cnd123 ', 'CND124']);

        $this->assertSame(['CND123', 'CND124'], Serie::query()->orderBy('numero')->pluck('numero')->all());
        $this->assertSame(2, Serie::query()->where('estado', Serie::EN_STOCK)->where('sede_id', $this->sede->id)->count());
    }

    public function test_no_entra_sin_series_ni_repetida_ni_dos_veces(): void
    {
        $this->assertStringContainsString('escribe las 2 series', $this->errorDe(fn () => $this->inventario->entrada($this->sede->id, [
            ['variante_id' => $this->laptop->id, 'cantidad' => 2, 'costo_unitario' => 2000, 'series' => ['A1']],
        ], null, null, null)));
        $this->assertStringContainsString('repetida', $this->errorDe(fn () => $this->entrar(['A1', 'a1'])));

        $this->entrar(['A1']);
        $this->assertStringContainsString('ya está en stock', $this->errorDe(fn () => $this->entrar(['A1'])));
        // Nada quedó a medias: el stock es el de la única entrada buena.
        $this->assertEquals(1, $this->laptop->fresh()->stock);
    }

    public function test_sale_la_serie_elegida_o_la_primera_que_entro(): void
    {
        $this->entrar(['S1', 'S2', 'S3']);

        $this->inventario->salida($this->sede->id, [['variante_id' => $this->laptop->id, 'cantidad' => 1, 'series' => ['S3']]],
            MovimientoInventario::MOTIVO_VENTA, 'P-000001', null, null);
        $this->assertSame(Serie::VENDIDA, Serie::query()->where('numero', 'S3')->value('estado'));

        $this->inventario->salida($this->sede->id, [['variante_id' => $this->laptop->id, 'cantidad' => 1]], 'merma', null, null, null);
        $this->assertSame(Serie::BAJA, Serie::query()->where('numero', 'S1')->value('estado'));

        $this->assertStringContainsString('no está en stock', $this->errorDe(fn () => $this->inventario->salida($this->sede->id,
            [['variante_id' => $this->laptop->id, 'cantidad' => 1, 'series' => ['S3']]], MovimientoInventario::MOTIVO_VENTA, null, null, null)));
    }

    public function test_el_traslado_lleva_las_mismas_series(): void
    {
        $otra = Sede::create(['nombre' => 'Juliaca']);
        $this->entrar(['T1', 'T2']);

        $this->inventario->traslado($this->sede->id, $otra->id, [['variante_id' => $this->laptop->id, 'cantidad' => 1, 'series' => ['T2']]], null, null, null);

        $this->assertSame($otra->id, Serie::query()->where('numero', 'T2')->value('sede_id'));
        $this->assertSame(Serie::EN_STOCK, Serie::query()->where('numero', 'T2')->value('estado'));
        $this->assertSame($this->sede->id, Serie::query()->where('numero', 'T1')->value('sede_id'));
    }

    public function test_el_pedido_entrega_las_series_elegidas_y_al_cancelar_vuelven(): void
    {
        $this->entrar(['P1', 'P2', 'P3']);
        $usuario = $this->actuarCon('pedidos.store', 'pedidos.confirmar', 'pedidos.cancelar');
        $usuario->forceFill(['sede_id' => $this->sede->id])->save();
        \App\Models\Stock::query()->where('variante_id', $this->laptop->id)->update(['activo' => true]);

        $id = $this->postJson('/api/pedidos', ['pedido' => [
            'canal' => 'mostrador',
            'items' => [['variante_id' => $this->laptop->id, 'cantidad' => 2, 'precio_unitario' => 2500, 'series' => ['p3', 'P2']]],
        ]])->assertCreated()->assertJsonPath('items.0.series', ['P3', 'P2'])->json('id');

        $this->postJson("/api/pedidos/{$id}/confirmar")->assertOk();
        $this->assertEqualsCanonicalizing(['P2', 'P3'], Serie::query()->where('estado', Serie::VENDIDA)->pluck('numero')->all());

        $this->postJson("/api/pedidos/{$id}/cancelar")->assertOk();
        $this->assertSame(3, Serie::query()->where('estado', Serie::EN_STOCK)->count());
    }

    public function test_la_consulta_dice_a_quien_se_vendio_y_su_garantia(): void
    {
        $this->entrar(['G1']);
        $pedido = (new \App\Models\Pedido)->forceFill([
            'codigo' => 'P-000009', 'cliente_id' => Cliente::create(['nombre' => 'Ana Quispe', 'numero_documento' => '40111222'])->id,
            'sede_id' => $this->sede->id, 'estado' => 'confirmado', 'canal' => 'mostrador', 'subtotal' => 2500, 'total' => 2500,
        ]);
        $pedido->save();
        $this->inventario->salida($this->sede->id, [['variante_id' => $this->laptop->id, 'cantidad' => 1, 'series' => ['G1']]],
            MovimientoInventario::MOTIVO_VENTA, 'P-000009', null, null, ['pedido_id' => $pedido->id]);

        $this->actuarCon('ventas.store');
        $id = $this->getJson('/api/series?search=g1')->assertOk()->assertJsonPath('data.0.numero', 'G1')->json('data.0.id');

        $this->getJson("/api/series/{$id}")
            ->assertOk()
            ->assertJsonPath('estado', 'vendida')
            ->assertJsonPath('venta.cliente.nombre', 'Ana Quispe')
            ->assertJsonPath('venta.pedido', 'P-000009')
            ->assertJsonPath('garantia_meses', 12)
            ->assertJsonPath('en_garantia', true)
            ->assertJsonCount(2, 'historia');
    }
}
