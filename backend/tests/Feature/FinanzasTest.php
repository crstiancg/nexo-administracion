<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\ConceptoFinanciero;
use App\Models\MovimientoCaja;
use App\Models\Sede;
use App\Models\TipoEquipo;
use App\Models\User;
use App\Services\Cajas;
use App\Support\Fechas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class FinanzasTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Sede $sede;

    private ConceptoFinanciero $alquiler;

    private ConceptoFinanciero $prestamo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sede = Sede::create(['nombre' => 'Matriz']);
        $this->alquiler = ConceptoFinanciero::create(['tipo' => 'gasto', 'nombre' => 'Alquiler del local']);
        $this->prestamo = ConceptoFinanciero::create(['tipo' => 'ingreso', 'nombre' => 'Préstamo recibido']);
    }

    private function usuario(string ...$permisos): User
    {
        $user = $this->actuarCon(...$permisos);
        $user->forceFill(['sede_id' => $this->sede->id])->save();

        return $user;
    }

    /** @return array<string, mixed> */
    private function gasto(array $cambios = []): array
    {
        return ['movimiento' => array_merge([
            'concepto_id' => $this->alquiler->id,
            'monto' => 800,
            'metodo' => 'transferencia',
            'fecha' => Fechas::hoyIso(),
            'descripcion' => 'Alquiler de noviembre',
        ], $cambios)];
    }

    public function test_registra_un_gasto_que_no_pasa_por_la_caja_y_se_puede_eliminar(): void
    {
        $this->usuario('movimientos-financieros.store', 'movimientos-financieros.index', 'movimientos-financieros.destroy');

        $id = $this->postJson('/api/movimientos-financieros', $this->gasto())
            ->assertCreated()
            ->assertJsonPath('tipo', 'gasto')
            ->assertJsonPath('movimiento_caja_id', null)
            ->json('id');

        $this->getJson('/api/movimientos-financieros')
            ->assertOk()
            ->assertJsonPath('totales.gastos', 800)
            ->assertJsonPath('totales.ingresos', 0);

        $this->deleteJson("/api/movimientos-financieros/{$id}")->assertNoContent();
    }

    public function test_un_gasto_en_efectivo_de_la_caja_sale_del_cajon_y_ya_no_se_elimina(): void
    {
        $user = $this->usuario('movimientos-financieros.store', 'movimientos-financieros.destroy');
        app(Cajas::class)->abrir($this->sede->id, 500, $user);

        $id = $this->postJson('/api/movimientos-financieros', $this->gasto([
            'concepto_id' => $this->alquiler->id, 'monto' => 120, 'metodo' => 'efectivo', 'de_caja' => true, 'descripcion' => 'Luz',
        ]))->assertCreated()->json('id');

        $movimiento = MovimientoCaja::firstOrFail();
        $this->assertSame(MovimientoCaja::EGRESO, $movimiento->tipo);
        $this->assertEquals(120, $movimiento->monto);
        $this->assertSame(380.0, app(Cajas::class)->resumen(Caja::firstOrFail())['efectivo_esperado']);

        $this->deleteJson("/api/movimientos-financieros/{$id}")->assertStatus(409);
    }

    public function test_no_sale_de_la_caja_mas_efectivo_del_que_hay(): void
    {
        $user = $this->usuario('movimientos-financieros.store');
        app(Cajas::class)->abrir($this->sede->id, 50, $user);

        // El error va al monto, para verlo en el formulario.
        $this->postJson('/api/movimientos-financieros', $this->gasto(['monto' => 200, 'metodo' => 'efectivo', 'de_caja' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('movimiento.monto');
        $this->assertDatabaseCount('movimientos_financieros', 0);
    }

    public function test_la_fecha_no_puede_ser_futura_ni_el_concepto_inactivo(): void
    {
        $this->usuario('movimientos-financieros.store');
        $this->alquiler->update(['activo' => false]);

        $this->postJson('/api/movimientos-financieros', $this->gasto(['fecha' => Fechas::hoy()->addDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['movimiento.fecha', 'movimiento.concepto_id']);
    }

    public function test_las_ganancias_restan_costo_y_gastos_y_suman_otros_ingresos(): void
    {
        $user = $this->usuario('movimientos-financieros.store', 'ganancias.index', 'ordenes-servicio.store', 'ordenes-servicio.estado', 'pedidos.pagos');
        app(Cajas::class)->abrir($this->sede->id, 0, $user);

        // Una reparación cobrada y entregada: S/ 150 de mano de obra.
        $orden = $this->postJson('/api/ordenes-servicio', ['orden' => [
            'cliente_id' => Cliente::create(['nombre' => 'Ana'])->id,
            'tipo_equipo_id' => TipoEquipo::create(['nombre' => 'Laptop', 'activo' => true])->id,
            'seguridad_tipo' => 'ninguno',
            'falla' => 'No enciende',
            'mano_obra' => 150,
            'tecnico_id' => $user->id,
            'repuestos' => [],
        ]])->assertCreated()->json();
        $this->postJson("/api/pedidos/{$orden['pedido_id']}/pagos", ['pago' => ['metodo' => 'efectivo', 'monto' => 150]])->assertCreated();
        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'reparado'])->assertOk();
        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'entregado'])->assertOk();

        $this->postJson('/api/movimientos-financieros', $this->gasto(['monto' => 100]))->assertCreated();
        $this->postJson('/api/movimientos-financieros', $this->gasto(['concepto_id' => $this->prestamo->id, 'monto' => 30]))->assertCreated();

        $this->getJson('/api/ganancias')
            ->assertOk()
            ->assertJsonPath('reparaciones.total', 150)
            ->assertJsonPath('ventas.total', 0)
            ->assertJsonPath('otros_ingresos', 30)
            ->assertJsonPath('ingresos', 180)
            ->assertJsonPath('gastos', 100)
            ->assertJsonPath('utilidad', 80)
            ->assertJsonPath('gastos_por_concepto.0.concepto', 'Alquiler del local')
            ->assertJsonPath('por_tecnico.0.ordenes', 1)
            ->assertJsonPath('por_tecnico.0.ganancia', 150);
    }

    public function test_sin_permiso_no_ve_las_ganancias(): void
    {
        $this->usuario('pedidos.index');

        $this->getJson('/api/ganancias')->assertForbidden();
        $this->getJson('/api/movimientos-financieros')->assertForbidden();
    }

    public function test_los_conceptos_se_crean_sin_repetir_nombre_y_no_se_borran_si_tienen_movimientos(): void
    {
        $this->usuario('conceptos-financieros.store', 'conceptos-financieros.destroy', 'movimientos-financieros.store');

        $this->postJson('/api/conceptos-financieros', ['concepto' => ['tipo' => 'gasto', 'nombre' => 'Alquiler del local']])
            ->assertUnprocessable();
        // El mismo nombre como ingreso sí vale (otro tipo).
        $this->postJson('/api/conceptos-financieros', ['concepto' => ['tipo' => 'ingreso', 'nombre' => 'Alquiler del local']])
            ->assertCreated();

        $this->postJson('/api/movimientos-financieros', $this->gasto())->assertCreated();
        $this->deleteJson("/api/conceptos-financieros/{$this->alquiler->id}")->assertStatus(409);
    }
}
