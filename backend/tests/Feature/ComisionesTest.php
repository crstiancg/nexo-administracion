<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MovimientoFinanciero;
use App\Models\Sede;
use App\Models\TipoEquipo;
use App\Models\User;
use App\Services\Cajas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class ComisionesTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $sede = Sede::create(['nombre' => 'Matriz']);
        $this->user = $this->actuarCon(
            'ordenes-servicio.store', 'ordenes-servicio.estado', 'pedidos.pagos',
            'comisiones.index', 'comisiones.porcentajes', 'comisiones.pagar',
        );
        $this->user->forceFill(['sede_id' => $sede->id])->save();
        app(Cajas::class)->abrir($sede->id, 0, $this->user);
    }

    /** Una orden entregada por el usuario con S/ 200 de mano de obra. */
    private function reparacionEntregada(): void
    {
        $orden = $this->postJson('/api/ordenes-servicio', ['orden' => [
            'cliente_id' => Cliente::create(['nombre' => 'Ana'])->id,
            'tipo_equipo_id' => TipoEquipo::firstOrCreate(['nombre' => 'Laptop'], ['activo' => true])->id,
            'tecnico_id' => $this->user->id,
            'seguridad_tipo' => 'ninguno',
            'falla' => 'No enciende',
            'mano_obra' => 200,
            'repuestos' => [],
        ]])->assertCreated()->json();
        $this->postJson("/api/pedidos/{$orden['pedido_id']}/pagos", ['pago' => ['metodo' => 'efectivo', 'monto' => 200]])->assertCreated();
        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'reparado'])->assertOk();
        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'entregado'])->assertOk();
    }

    public function test_calcula_el_porcentaje_sobre_la_mano_de_obra_entregada(): void
    {
        $this->reparacionEntregada();
        $this->putJson('/api/comisiones/porcentajes', ['empleados' => [['id' => $this->user->id, 'reparacion' => 15, 'venta' => 0]]])
            ->assertOk();

        $fila = collect($this->getJson('/api/comisiones')->assertOk()->json('data'))->firstWhere('id', $this->user->id);

        $this->assertSame(1, $fila['reparaciones']);
        $this->assertEquals(200, $fila['base_reparacion']);
        $this->assertEquals(30, $fila['comision_reparacion']);
        $this->assertEquals(30, $fila['total']);
        $this->assertNull($fila['pagado']);
    }

    public function test_pagar_la_registra_como_gasto_y_no_se_paga_dos_veces(): void
    {
        $this->reparacionEntregada();
        $this->user->forceFill(['comision_reparacion' => 10])->save();

        $respuesta = $this->postJson('/api/comisiones/pagar', ['user_id' => $this->user->id, 'pago' => ['metodo' => 'transferencia']])
            ->assertOk();
        $this->assertEquals(20, collect($respuesta->json('data'))->firstWhere('id', $this->user->id)['pagado']);

        $gasto = MovimientoFinanciero::with('concepto')->firstOrFail();
        $this->assertSame('Comisiones', $gasto->concepto->nombre);
        $this->assertEquals(20, $gasto->monto);

        $this->postJson('/api/comisiones/pagar', ['user_id' => $this->user->id, 'pago' => ['metodo' => 'transferencia']])
            ->assertStatus(409);
    }

    public function test_sin_comision_no_hay_nada_que_pagar(): void
    {
        $this->postJson('/api/comisiones/pagar', ['user_id' => $this->user->id, 'pago' => ['metodo' => 'efectivo']])
            ->assertStatus(409);
    }

    public function test_sin_permiso_no_ve_las_comisiones(): void
    {
        $this->actuarCon('pedidos.index');

        $this->getJson('/api/comisiones')->assertForbidden();
    }
}
