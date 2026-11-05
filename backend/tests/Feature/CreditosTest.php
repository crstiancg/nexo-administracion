<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Pedido;
use App\Models\Sede;
use App\Models\TipoEquipo;
use App\Models\User;
use App\Services\Cajas;
use App\Support\Fechas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class CreditosTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Sede $sede;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Sede::create(['nombre' => 'Matriz']);
        $this->user = $this->actuarCon(
            'ordenes-servicio.store', 'ordenes-servicio.estado', 'pedidos.pagos', 'creditos.index', 'creditos.store',
        );
        $this->user->forceFill(['sede_id' => $this->sede->id])->save();
        app(Cajas::class)->abrir($this->sede->id, 0, $this->user);
    }

    /** Una orden de S/ 300 de mano de obra, reparada, con S/ 60 de adelanto. */
    private function ordenReparada(): array
    {
        $orden = $this->postJson('/api/ordenes-servicio', ['orden' => [
            'cliente_id' => Cliente::create(['nombre' => 'Ana Quispe', 'telefono' => '987654321'])->id,
            'tipo_equipo_id' => TipoEquipo::create(['nombre' => 'Laptop', 'activo' => true])->id,
            'seguridad_tipo' => 'ninguno',
            'falla' => 'Cambio de pantalla',
            'mano_obra' => 300,
            'repuestos' => [],
            'anticipo' => ['metodo' => 'efectivo', 'monto' => 60],
        ]])->assertCreated()->json();
        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'reparado'])->assertOk();

        return $orden;
    }

    private function darCredito(int $pedidoId, array $cambios = []): array
    {
        return $this->postJson('/api/creditos', ['credito' => array_merge([
            'pedido_id' => $pedidoId,
            'frecuencia' => 'semanal',
            'numero_cuotas' => 3,
            'primer_vencimiento' => Fechas::hoy()->addDays(7)->toDateString(),
        ], $cambios)])->assertCreated()->json();
    }

    public function test_financia_el_saldo_en_cuotas_que_suman_exacto(): void
    {
        $orden = $this->ordenReparada();

        $credito = $this->darCredito($orden['pedido_id']);

        $this->assertSame('240.00', $credito['monto']);
        $this->assertCount(3, $credito['cuotas']);
        $this->assertEqualsWithDelta(240, array_sum(array_map(fn ($c) => (float) $c['monto'], $credito['cuotas'])), 0.001);
        // Semanales: una semana entre cada una.
        $this->assertSame(Fechas::hoy()->addDays(14)->toDateString(), $credito['cuotas'][1]['vence']);
        $this->assertSame('pendiente', $credito['cuotas'][0]['estado']);
    }

    public function test_sin_credito_no_se_entrega_con_saldo_y_con_credito_si(): void
    {
        $orden = $this->ordenReparada();

        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'entregado'])->assertStatus(409);

        $this->darCredito($orden['pedido_id']);
        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'entregado'])->assertOk();
        $this->assertSame(Pedido::ENTREGADO, Pedido::find($orden['pedido_id'])->estado);
    }

    public function test_entregado_a_credito_se_sigue_cobrando_y_los_pagos_cubren_las_cuotas_en_orden(): void
    {
        $orden = $this->ordenReparada();
        $credito = $this->darCredito($orden['pedido_id']);
        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'entregado'])->assertOk();

        // 80 por cuota: un pago de 100 cubre la 1 y parte de la 2.
        $this->postJson("/api/pedidos/{$orden['pedido_id']}/pagos", ['pago' => ['metodo' => 'efectivo', 'monto' => 100]])->assertCreated();

        $detalle = $this->getJson("/api/creditos/{$credito['id']}")->assertOk()->json();
        $this->assertSame('100.00', $detalle['pagado']);
        $this->assertSame('140.00', $detalle['saldo']);
        $this->assertSame(['pagada', 'parcial', 'pendiente'], array_column($detalle['cuotas'], 'estado'));
        $this->assertSame(2, $detalle['proxima']['numero']);
        // El adelanto de 60 y el pago de 100.
        $this->assertCount(2, $detalle['pagos']);

        // Paga el resto: queda saldado y no acepta más.
        $this->postJson("/api/pedidos/{$orden['pedido_id']}/pagos", ['pago' => ['metodo' => 'efectivo', 'monto' => 140]])->assertCreated();
        $this->getJson("/api/creditos/{$credito['id']}")->assertJsonPath('saldo', '0.00');
        $this->postJson("/api/pedidos/{$orden['pedido_id']}/pagos", ['pago' => ['metodo' => 'efectivo', 'monto' => 1]])->assertUnprocessable();
    }

    public function test_las_cuotas_vencidas_aparecen_en_el_listado_y_la_campana(): void
    {
        $orden = $this->ordenReparada();
        $credito = $this->darCredito($orden['pedido_id']);
        Credito::find($credito['id'])->cuotas()->where('numero', 1)->update(['vence' => Fechas::hoy()->subDays(3)->toDateString()]);

        $this->getJson('/api/creditos?estado=vencidos')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.cuotas_vencidas', 1)
            ->assertJsonPath('resumen.vencido', 80)
            ->assertJsonPath('resumen.por_cobrar', 240);

        $claves = collect($this->getJson('/api/panel/alertas')->json('data'))->pluck('clave');
        $this->assertTrue($claves->contains('creditos_vencidos'));
    }

    public function test_las_cuotas_que_vencen_pronto_se_listan_para_recordarlas(): void
    {
        $orden = $this->ordenReparada();
        // La primera vence en 2 días: entra en "por vencer" (3 días).
        $credito = $this->darCredito($orden['pedido_id'], ['primer_vencimiento' => Fechas::hoy()->addDays(2)->toDateString()]);

        $this->getJson('/api/creditos?estado=por_vencer')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.proxima.numero', 1)
            ->assertJsonPath('resumen.por_vencer', 1);

        $claves = collect($this->getJson('/api/panel/alertas')->json('data'))->pluck('clave');
        $this->assertTrue($claves->contains('creditos_por_vencer'));

        // A una semana todavía no.
        Credito::find($credito['id'])->cuotas()->where('numero', 1)->update(['vence' => Fechas::hoy()->addDays(7)->toDateString()]);
        $this->getJson('/api/creditos?estado=por_vencer')->assertJsonPath('total', 0);
    }

    public function test_no_se_da_credito_dos_veces_ni_sin_saldo(): void
    {
        $orden = $this->ordenReparada();
        $this->darCredito($orden['pedido_id']);

        $this->postJson('/api/creditos', ['credito' => [
            'pedido_id' => $orden['pedido_id'], 'frecuencia' => 'mensual', 'numero_cuotas' => 2,
            'primer_vencimiento' => Fechas::hoyIso(),
        ]])->assertStatus(409);
    }

    public function test_sin_permiso_no_ve_los_creditos(): void
    {
        $this->actuarCon('pedidos.index');

        $this->getJson('/api/creditos')->assertForbidden();
    }
}
