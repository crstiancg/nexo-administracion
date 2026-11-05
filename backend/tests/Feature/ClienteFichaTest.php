<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Pedido;
use App\Models\Sede;
use App\Support\Fechas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * La ficha del cliente junta lo suyo de cada módulo, según los permisos.
 */
class ClienteFichaTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Cliente $cliente;

    private Sede $sede;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sede = Sede::create(['nombre' => 'Matriz']);
        $this->cliente = Cliente::create(['nombre' => 'Juan Pérez', 'numero_documento' => '40123456']);
    }

    private function pedido(string $estado, float $total): Pedido
    {
        $pedido = (new Pedido)->forceFill([
            'cliente_id' => $this->cliente->id,
            'sede_id' => $this->sede->id,
            'estado' => $estado,
            'canal' => 'mostrador',
            'subtotal' => $total,
            'total' => $total,
            'confirmado_at' => $estado === Pedido::PENDIENTE ? null : now(),
        ]);
        $pedido->save();

        return $pedido;
    }

    public function test_junta_compras_cotizaciones_y_lo_que_debe(): void
    {
        $this->actuarCon('clientes.index', 'pedidos.index', 'cotizaciones.index');
        // Sin pagos: estas dos las debe (500).
        $this->pedido(Pedido::ENTREGADO, 300);
        $this->pedido(Pedido::CONFIRMADO, 200);
        // Un pendiente no es compra.
        $this->pedido(Pedido::PENDIENTE, 999);
        $caja = (new Caja)->forceFill(['sede_id' => $this->sede->id, 'estado' => 'abierta', 'abierta' => true, 'monto_apertura' => 0, 'abierta_at' => now()]);
        $caja->save();
        $this->pedido(Pedido::ENTREGADO, 300)->pagos()->forceCreate(['monto' => 300, 'metodo' => 'efectivo', 'caja_id' => $caja->id]);
        (new Cotizacion)->forceFill([
            'codigo' => 'COT-000001', 'token' => str_repeat('a', 32), 'estado' => Cotizacion::PENDIENTE,
            'cliente_id' => $this->cliente->id, 'sede_id' => $this->sede->id,
            'valida_hasta' => Fechas::hoy()->addDays(7), 'subtotal' => 50, 'total' => 50,
        ])->save();

        $this->getJson("/api/clientes/{$this->cliente->id}/ficha")
            ->assertOk()
            ->assertJsonPath('cliente.nombre', 'Juan Pérez')
            ->assertJsonPath('resumen.compras', 3)
            ->assertJsonPath('resumen.total_comprado', 800)
            ->assertJsonPath('resumen.debe', 500)
            ->assertJsonCount(4, 'pedidos')
            ->assertJsonPath('cotizaciones.0.codigo', 'COT-000001')
            // Sin permiso de créditos ni de órdenes: esas secciones no vienen.
            ->assertJsonPath('creditos', null)
            ->assertJsonPath('ordenes', null);
    }

    public function test_sin_permiso_de_clientes_no_la_ve(): void
    {
        $this->actuarCon('pedidos.index');

        $this->getJson("/api/clientes/{$this->cliente->id}/ficha")->assertForbidden();
    }
}
