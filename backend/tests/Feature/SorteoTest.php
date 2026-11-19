<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\Sorteo;
use App\Models\SorteoTicket;
use App\Models\Stock;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Variante;
use App\Services\Cajas;
use App\Support\Fechas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * Sorteos de fidelización: cada venta entregada de un cliente registrado da
 * tickets (1 por cada S/ X), se anulan con la venta y los premios salen al
 * azar entre los tickets vigentes.
 */
class SorteoTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Sede $sede;

    private Variante $mouse;

    private Cliente $cliente;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sede = Sede::create(['nombre' => 'Matriz']);
        $producto = Producto::create([
            'nombre' => 'Mouse gamer',
            'categoria_id' => Categoria::create(['nombre' => 'Accesorios'])->id,
            'marca_id' => Marca::create(['nombre' => 'Logitech', 'activo' => true])->id,
            'precio' => 50,
        ]);
        $this->mouse = $producto->variantes()->create([
            'presentacion' => 'Unidad',
            'unidad_id' => Unidad::create(['nombre' => 'Unidad', 'abreviatura' => 'und'])->id,
            'sku' => 'MOUSE-1',
        ]);
        (new Stock)->forceFill(['sede_id' => $this->sede->id, 'variante_id' => $this->mouse->id, 'cantidad' => 100, 'activo' => true])->save();
        $this->cliente = Cliente::create(['nombre' => 'JUAN PEREZ QUISPE', 'tipo_documento' => 'DNI', 'numero_documento' => '45678912']);

        $this->usuario = $this->actuarCon('ventas.store', 'pedidos.anular', 'sorteos.index', 'sorteos.store', 'sorteos.update', 'sorteos.destroy', 'sorteos.cancelar', 'sorteos.sortear');
        $this->usuario->forceFill(['sede_id' => $this->sede->id])->save();
        app(Cajas::class)->abrir($this->sede->id, 0, $this->usuario);
    }

    private function crearSorteo(array $datos = []): TestResponse
    {
        return $this->postJson('/api/sorteos', ['sorteo' => $datos + [
            'nombre' => 'Sorteo de diciembre',
            'periodo' => 'mensual',
            'inicia_el' => Fechas::hoy()->subDays(3)->toDateString(),
            'termina_el' => Fechas::hoy()->addDays(10)->toDateString(),
            'monto_por_ticket' => 100,
            'premios' => [['descripcion' => 'Laptop'], ['descripcion' => 'Audífonos'], ['descripcion' => '']],
        ]]);
    }

    private function vender(int $cantidad, ?int $clienteId = null): TestResponse
    {
        $total = $cantidad * 50;

        return $this->postJson('/api/ventas', ['venta' => [
            'cliente_id' => $clienteId === 0 ? null : ($clienteId ?? $this->cliente->id),
            'items' => [['variante_id' => $this->mouse->id, 'cantidad' => $cantidad, 'precio_unitario' => 50]],
            'pagos' => [['metodo' => 'efectivo', 'monto' => $total, 'recibido' => $total]],
        ]]);
    }

    public function test_crea_el_sorteo_con_sus_premios_en_orden(): void
    {
        $this->crearSorteo()
            ->assertCreated()
            ->assertJsonPath('estado', 'abierto')
            ->assertJsonCount(2, 'premios')
            ->assertJsonPath('premios.0.orden', 1)
            ->assertJsonPath('premios.0.descripcion', 'Laptop')
            ->assertJsonPath('premios.1.orden', 2);
    }

    public function test_una_venta_da_un_ticket_por_cada_monto_y_sale_en_el_ticket(): void
    {
        $this->crearSorteo();

        // 7 × 50 = 350 → 3 tickets (lo que sobra no suma).
        $this->vender(7)
            ->assertCreated()
            ->assertJsonPath('sorteos.0.sorteo', 'Sorteo de diciembre')
            ->assertJsonPath('sorteos.0.cantidad', 3)
            ->assertJsonPath('sorteos.0.numeros', ['#000001', '#000002', '#000003']);

        // La numeración sigue en la próxima venta.
        $this->vender(2)->assertJsonPath('sorteos.0.numeros', ['#000004']);
    }

    public function test_respeta_el_maximo_por_compra(): void
    {
        $this->crearSorteo(['max_tickets_por_compra' => 2]);

        $this->vender(20)->assertJsonPath('sorteos.0.cantidad', 2);
    }

    public function test_sin_cliente_o_fuera_del_periodo_no_hay_tickets(): void
    {
        $this->crearSorteo(['inicia_el' => Fechas::hoy()->addDay()->toDateString()]);
        $this->vender(10)->assertJsonPath('sorteos', []);

        $this->crearSorteo(['nombre' => 'Otro']);
        $this->vender(10, 0)->assertJsonPath('sorteos', []);
    }

    public function test_solo_participan_las_sedes_elegidas(): void
    {
        $otra = Sede::create(['nombre' => 'Sucursal']);
        $this->crearSorteo(['sedes' => [$otra->id]]);

        $this->vender(10)->assertJsonPath('sorteos', []);
    }

    public function test_anular_la_venta_anula_sus_tickets(): void
    {
        $this->crearSorteo();
        $pedido = $this->vender(4)->json('id');

        $this->postJson("/api/pedidos/{$pedido}/anular", ['motivo' => 'Se arrepintió'])->assertOk();

        $this->assertSame(0, SorteoTicket::query()->vigentes()->count());
        $this->assertSame(2, SorteoTicket::count());
    }

    public function test_no_se_sortea_antes_del_ultimo_dia(): void
    {
        $sorteo = $this->crearSorteo()->json();
        $this->vender(4);

        $this->postJson("/api/sorteos/{$sorteo['id']}/premios/{$sorteo['premios'][0]['id']}/sortear")
            ->assertConflict()
            ->assertJsonValidationErrors('sorteo');
    }

    public function test_sortea_los_premios_sin_repetir_cliente_y_cierra_el_sorteo(): void
    {
        $sorteo = $this->crearSorteo(['termina_el' => Fechas::hoy()->toDateString()])->json();
        $otro = Cliente::create(['nombre' => 'ANA TORRES', 'tipo_documento' => 'DNI', 'numero_documento' => '11112222']);
        $this->vender(4);
        $this->vender(2, $otro->id);

        $primero = $this->postJson("/api/sorteos/{$sorteo['id']}/premios/{$sorteo['premios'][0]['id']}/sortear")
            ->assertOk()
            ->assertJsonPath('premio.participantes', 3)
            ->assertJsonPath('sorteo.estado', 'sorteando')
            ->json('premio.ganador');
        $this->assertMatchesRegularExpression('/^DNI \*{4}\d{4}$/', $primero['publico']['documento']);

        // Ya no entran tickets: empezó a sortearse.
        $this->vender(10)->assertJsonPath('sorteos', []);

        $segundo = $this->postJson("/api/sorteos/{$sorteo['id']}/premios/{$sorteo['premios'][1]['id']}/sortear")
            ->assertOk()
            ->assertJsonPath('sorteo.estado', 'sorteado')
            ->json('premio.ganador');

        // Un premio por cliente: el segundo es el otro cliente.
        $this->assertNotSame($primero['cliente']['id'], $segundo['cliente']['id']);
    }

    public function test_descartar_un_ganador_lo_deja_en_el_acta_y_vuelve_a_sortearse(): void
    {
        $sorteo = $this->crearSorteo(['termina_el' => Fechas::hoy()->toDateString(), 'un_premio_por_cliente' => false])->json();
        $this->vender(4);
        $premio = $sorteo['premios'][0]['id'];

        $ganador = $this->postJson("/api/sorteos/{$sorteo['id']}/premios/{$premio}/sortear")->json('premio.ganador');

        $this->postJson("/api/sorteos/{$sorteo['id']}/premios/{$premio}/descartar", ['motivo' => 'No se presentó'])
            ->assertOk()
            ->assertJsonPath('premio.ganador', null)
            ->assertJsonPath('premio.descartes.0.numero', $ganador['numero'])
            ->assertJsonPath('premio.descartes.0.motivo', 'No se presentó');

        // El descartado no vuelve a salir: queda el otro ticket.
        $nuevo = $this->postJson("/api/sorteos/{$sorteo['id']}/premios/{$premio}/sortear")
            ->assertOk()
            ->assertJsonPath('premio.participantes', 1)
            ->json('premio.ganador');
        $this->assertNotSame($ganador['ticket_id'], $nuevo['ticket_id']);
    }

    public function test_participantes_y_excel(): void
    {
        $sorteo = $this->crearSorteo()->json('id');
        $this->vender(6);

        $this->getJson("/api/sorteos/{$sorteo}/participantes")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.tickets', 3)
            ->assertJsonPath('data.0.probabilidad', 100);

        $this->getJson("/api/sorteos/{$sorteo}/tickets?search=juan")->assertOk()->assertJsonPath('total', 3);

        $this->get("/api/sorteos/{$sorteo}/excel")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_con_tickets_no_se_borra_se_cancela(): void
    {
        $sorteo = $this->crearSorteo()->json('id');
        $this->vender(4);

        $this->deleteJson("/api/sorteos/{$sorteo}")->assertConflict();
        $this->postJson("/api/sorteos/{$sorteo}/cancelar")->assertOk()->assertJsonPath('estado', 'cancelado');
        $this->vender(4)->assertJsonPath('sorteos', []);
    }

    public function test_sin_permiso_no_se_sortea(): void
    {
        $sorteo = $this->crearSorteo(['termina_el' => Fechas::hoy()->toDateString()])->json();
        $this->actuarCon('sorteos.index');

        $this->postJson("/api/sorteos/{$sorteo['id']}/premios/{$sorteo['premios'][0]['id']}/sortear")->assertForbidden();
        $this->assertSame(Sorteo::ABIERTO, Sorteo::find($sorteo['id'])->estado);
    }
}
