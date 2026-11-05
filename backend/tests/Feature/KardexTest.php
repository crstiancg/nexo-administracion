<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\Unidad;
use App\Models\Variante;
use App\Services\Inventario;
use App\Support\Fechas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * Kardex valorizado: entradas a su costo, salidas al promedio ponderado del
 * momento y el saldo en cantidad y en soles.
 */
class KardexTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Sede $sede;

    private Variante $disco;

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

        $inventario = app(Inventario::class);
        // 10 a 100 y 10 a 130: promedio 115. Sale 4 a 115.
        $inventario->entrada($this->sede->id, [['variante_id' => $this->disco->id, 'cantidad' => 10, 'costo_unitario' => 100]], 'F001-1', null, null);
        $inventario->entrada($this->sede->id, [['variante_id' => $this->disco->id, 'cantidad' => 10, 'costo_unitario' => 130]], 'F001-2', null, null);
        $inventario->salida($this->sede->id, [['variante_id' => $this->disco->id, 'cantidad' => 4]], 'merma', null, null, null);
    }

    public function test_valoriza_al_costo_promedio_ponderado(): void
    {
        $this->actuarCon('reportes.inventario');

        $respuesta = $this->getJson("/api/reportes/kardex?variante_id={$this->disco->id}")->assertOk();

        $filas = $respuesta->json('filas');
        $this->assertCount(3, $filas);
        $this->assertEquals(1000, $filas[0]['importe']);
        $this->assertEquals(115, $filas[1]['costo_promedio']);
        // La salida al promedio del momento: 4 × 115.
        $this->assertEquals(4, $filas[2]['salida']);
        $this->assertEquals(460, $filas[2]['importe']);
        $this->assertEquals(16, $filas[2]['saldo']);
        $this->assertEquals(1840, $filas[2]['saldo_valor']);
        $respuesta->assertJsonPath('totales.entradas', 20)->assertJsonPath('saldo_final.valor', 1840);
    }

    public function test_lo_anterior_al_periodo_va_como_saldo_inicial(): void
    {
        $this->actuarCon('reportes.inventario');
        // Las dos compras fueron "ayer".
        MovimientoInventario::query()->where('tipo', MovimientoInventario::ENTRADA)
            ->update(['created_at' => Fechas::hoy()->subDay()->setTime(10, 0)]);

        $this->getJson("/api/reportes/kardex?variante_id={$this->disco->id}&desde=".Fechas::hoyIso())
            ->assertOk()
            ->assertJsonPath('saldo_inicial.cantidad', 20)
            ->assertJsonPath('saldo_inicial.valor', 2300)
            ->assertJsonCount(1, 'filas');
    }

    public function test_se_baja_en_excel(): void
    {
        $this->actuarCon('reportes.inventario');

        $this->get("/api/reportes/kardex/excel?variante_id={$this->disco->id}")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_sin_permiso_de_ver_costos_no_lo_ve(): void
    {
        $this->actuarCon('inventario.index');

        $this->getJson("/api/reportes/kardex?variante_id={$this->disco->id}")->assertForbidden();
    }
}
