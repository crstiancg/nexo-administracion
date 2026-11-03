<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Sede;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class PedidosListadoTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Sede $sede;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Sede::create(['nombre' => 'Matriz']);
    }

    private function pedido(string $estado, string $fecha, float $total = 100): Pedido
    {
        $pedido = new Pedido(['cliente_id' => Cliente::create(['nombre' => 'Ana Quispe'])->id, 'canal' => 'mostrador']);
        $pedido->forceFill([
            'sede_id' => $this->sede->id,
            'estado' => $estado,
            'subtotal' => $total,
            'total' => $total,
        ])->save();
        $pedido->forceFill(['codigo' => 'P-'.str_pad((string) $pedido->id, 6, '0', STR_PAD_LEFT)])->save();
        Pedido::query()->whereKey($pedido->id)->update(['created_at' => $fecha.' 15:00:00']);

        return $pedido;
    }

    public function test_filtra_por_fechas_y_cuenta_lo_que_requiere_atencion(): void
    {
        $this->actuarCon('pedidos.index');
        $this->pedido(Pedido::PENDIENTE, '2026-10-05');
        $this->pedido(Pedido::CONFIRMADO, '2026-10-20');
        $this->pedido(Pedido::ENTREGADO, '2026-11-02');

        $this->getJson('/api/pedidos?desde=2026-10-01&hasta=2026-10-31')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('atencion.pendientes', 1)
            ->assertJsonPath('atencion.por_entregar', 1);

        $this->getJson('/api/pedidos?desde=2026-11-01&hasta=2026-11-30')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_exporta_el_filtro_a_excel(): void
    {
        $this->actuarCon('pedidos.index');
        $this->pedido(Pedido::ENTREGADO, '2026-10-20', 250);

        $respuesta = $this->get('/api/pedidos/exportar?desde=2026-10-01&hasta=2026-10-31');

        $respuesta->assertOk();
        $this->assertStringContainsString('spreadsheetml', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('pedidos_2026-10-01_a_2026-10-31.xlsx', $respuesta->headers->get('Content-Disposition'));
    }

    public function test_sin_permiso_no_exporta(): void
    {
        $this->actuarCon('clientes.index');

        $this->get('/api/pedidos/exportar', ['Accept' => 'application/json'])->assertForbidden();
    }
}
