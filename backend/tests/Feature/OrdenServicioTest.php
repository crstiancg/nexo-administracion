<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\OrdenServicio;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\Stock;
use App\Models\TipoEquipo;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Variante;
use App\Services\Cajas;
use App\Services\Inventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class OrdenServicioTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Sede $sede;

    private Cliente $cliente;

    private TipoEquipo $laptop;

    private Variante $disco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sede = Sede::create(['nombre' => 'Matriz']);
        $this->cliente = Cliente::create(['nombre' => 'Ana Quispe', 'telefono' => '987654321']);
        $this->laptop = TipoEquipo::create(['nombre' => 'Laptop', 'checklist' => ['Enciende', 'Cargador'], 'activo' => true]);

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
        app(Inventario::class)->entrada($this->sede->id, [
            ['variante_id' => $this->disco->id, 'cantidad' => 5, 'costo_unitario' => 100],
        ], 'INICIAL', null, null);
        Stock::query()->where('variante_id', $this->disco->id)->update(['activo' => true]);
    }

    /** Un usuario de la sede con estos permisos (y la caja abierta). */
    private function recepcion(string ...$permisos): User
    {
        $user = $this->actuarCon(...$permisos);
        $user->forceFill(['sede_id' => $this->sede->id])->save();

        return $user;
    }

    private function abrirCaja(User $user): void
    {
        app(Cajas::class)->abrir($this->sede->id, 0, $user);
    }

    /** @return array<string, mixed> */
    private function payload(array $cambios = []): array
    {
        return ['orden' => array_merge([
            'cliente_id' => $this->cliente->id,
            'tipo_equipo_id' => $this->laptop->id,
            'modelo' => 'IdeaPad 3',
            'serie' => 'PF3XYZ',
            'seguridad_tipo' => 'pin',
            'seguridad_valor' => '1234',
            'checklist' => ['Enciende' => true, 'Cargador' => false],
            'falla' => 'No enciende',
            'garantia_dias' => 30,
            'mano_obra' => 80,
            'repuestos' => [
                ['variante_id' => $this->disco->id, 'cantidad' => 1, 'precio_unitario' => 150],
            ],
        ], $cambios)];
    }

    public function test_recibe_el_equipo_y_arma_el_cobro_con_repuestos_y_mano_de_obra(): void
    {
        $user = $this->recepcion('ordenes-servicio.store');
        $this->abrirCaja($user);

        $respuesta = $this->postJson('/api/ordenes-servicio', $this->payload([
            'anticipo' => ['metodo' => 'efectivo', 'monto' => 50],
        ]))->assertCreated();

        $respuesta->assertJsonPath('codigo', 'OS-'.str_pad((string) $respuesta->json('id'), 6, '0', STR_PAD_LEFT))
            ->assertJsonPath('estado', OrdenServicio::RECIBIDO)
            ->assertJsonPath('total', '230.00')
            ->assertJsonPath('pagado', '50.00')
            ->assertJsonPath('saldo', '180.00')
            ->assertJsonCount(1, 'repuestos')
            ->assertJsonCount(1, 'movimientos');

        $orden = OrdenServicio::firstOrFail();
        $this->assertSame('1234', $orden->seguridad_valor);
        // La clave del cliente no queda en texto plano en la base.
        $this->assertNotSame('1234', $orden->getRawOriginal('seguridad_valor'));

        $pedido = $orden->pedido;
        $this->assertSame(Pedido::CANAL_SERVICIO, $pedido->canal);
        $this->assertSame(Pedido::PENDIENTE, $pedido->estado);
        $this->assertCount(2, $pedido->items);
        $this->assertNotNull($pedido->items->firstWhere('variante_id', null)?->descripcion);

        // Recibir no toca el stock: los repuestos salen al entregar.
        $this->assertEquals(5, Stock::where('variante_id', $this->disco->id)->value('cantidad'));
    }

    public function test_editar_rearma_el_cobro(): void
    {
        $this->recepcion('ordenes-servicio.store', 'ordenes-servicio.update');
        $id = $this->postJson('/api/ordenes-servicio', $this->payload())->json('id');

        $this->putJson("/api/ordenes-servicio/{$id}", $this->payload(['mano_obra' => 120, 'repuestos' => []]))
            ->assertOk()
            ->assertJsonPath('total', '120.00')
            ->assertJsonCount(0, 'repuestos');
    }

    public function test_no_se_entrega_con_saldo_pendiente_y_no_descuenta_nada(): void
    {
        $this->recepcion('ordenes-servicio.store', 'ordenes-servicio.estado');
        $id = $this->postJson('/api/ordenes-servicio', $this->payload())->json('id');

        $this->postJson("/api/ordenes-servicio/{$id}/estado", ['estado' => 'reparado'])->assertOk();
        $this->postJson("/api/ordenes-servicio/{$id}/estado", ['estado' => 'entregado'])
            ->assertStatus(409)
            ->assertJsonValidationErrors('estado');

        $this->assertSame(OrdenServicio::REPARADO, OrdenServicio::find($id)->estado);
        $this->assertSame(Pedido::PENDIENTE, OrdenServicio::find($id)->pedido->estado);
        $this->assertEquals(5, Stock::where('variante_id', $this->disco->id)->value('cantidad'));
    }

    public function test_cobrado_todo_se_entrega_y_salen_los_repuestos(): void
    {
        $user = $this->recepcion('ordenes-servicio.store', 'ordenes-servicio.estado', 'pedidos.pagos');
        $this->abrirCaja($user);
        $orden = $this->postJson('/api/ordenes-servicio', $this->payload())->json();

        $this->postJson("/api/pedidos/{$orden['pedido_id']}/pagos", [
            'pago' => ['metodo' => 'yape', 'monto' => 230, 'referencia' => '123456'],
        ])->assertCreated();

        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'reparado'])->assertOk();
        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'entregado', 'nota' => 'Retira la hermana'])
            ->assertOk()
            ->assertJsonPath('estado', OrdenServicio::ENTREGADO)
            ->assertJsonPath('saldo', '0.00')
            ->assertJsonPath('editable', false);

        $entregada = OrdenServicio::find($orden['id']);
        $this->assertSame(Pedido::ENTREGADO, $entregada->pedido->estado);
        $this->assertNotNull($entregada->garantiaHasta());
        $this->assertEquals(4, Stock::where('variante_id', $this->disco->id)->value('cantidad'));
        // La mano de obra no tiene costo de mercadería: la ganancia se calcula.
        $this->assertSame('0.0000', (string) $entregada->pedido->items->firstWhere('variante_id', null)->costo_unitario);

        // Entregada: ni se edita ni cambia de estado.
        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'reparado'])->assertStatus(409);
    }

    public function test_solo_se_entrega_un_equipo_listo(): void
    {
        $this->recepcion('ordenes-servicio.store', 'ordenes-servicio.estado');
        $id = $this->postJson('/api/ordenes-servicio', $this->payload(['mano_obra' => 0, 'repuestos' => []]))->json('id');

        $this->postJson("/api/ordenes-servicio/{$id}/estado", ['estado' => 'entregado'])->assertStatus(409);
    }

    public function test_el_pedido_de_una_orden_no_se_maneja_desde_pedidos(): void
    {
        $this->recepcion('ordenes-servicio.store', 'pedidos.confirmar', 'pedidos.cancelar');
        $pedidoId = $this->postJson('/api/ordenes-servicio', $this->payload())->json('pedido_id');

        $this->postJson("/api/pedidos/{$pedidoId}/confirmar")->assertStatus(409);
        $this->postJson("/api/pedidos/{$pedidoId}/cancelar")->assertStatus(409);
    }

    public function test_el_seguimiento_publico_no_muestra_la_clave(): void
    {
        $this->recepcion('ordenes-servicio.store');
        $orden = $this->postJson('/api/ordenes-servicio', $this->payload())->json();

        // Sin sesión.
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/seguimiento/{$orden['token']}")
            ->assertOk()
            ->assertJsonPath('codigo', $orden['codigo'])
            ->assertJsonPath('estado_label', 'Recibido')
            ->assertJsonPath('saldo', '230.00')
            ->assertJsonMissingPath('seguridad_valor')
            ->assertJsonMissingPath('cliente');

        $this->getJson('/api/seguimiento/'.str_repeat('a', 32))->assertNotFound();
    }

    public function test_pide_la_clave_si_el_equipo_tiene_bloqueo(): void
    {
        $this->recepcion('ordenes-servicio.store');

        $this->postJson('/api/ordenes-servicio', $this->payload(['seguridad_valor' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('orden.seguridad_valor');
    }

    public function test_sin_permiso_no_ve_las_ordenes(): void
    {
        $this->recepcion('pedidos.index');

        $this->getJson('/api/ordenes-servicio')->assertForbidden();
    }

    public function test_el_listado_filtra_las_activas_y_resume_por_estado(): void
    {
        $this->recepcion('ordenes-servicio.store', 'ordenes-servicio.index', 'ordenes-servicio.estado');
        $id = $this->postJson('/api/ordenes-servicio', $this->payload())->json('id');
        $this->postJson('/api/ordenes-servicio', $this->payload());
        $this->postJson("/api/ordenes-servicio/{$id}/estado", ['estado' => 'abandonado'])->assertOk();

        $this->getJson('/api/ordenes-servicio?estado=activas')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/ordenes-servicio?search=PF3XYZ')->assertOk()->assertJsonPath('total', 2);

        $resumen = collect($this->getJson('/api/ordenes-servicio/resumen')->assertOk()->json())->pluck('total', 'estado');
        $this->assertSame(1, $resumen['recibido']);
        $this->assertSame(1, $resumen['abandonado']);
    }

    public function test_imprime_la_orden_y_la_etiqueta_con_enlace_firmado_y_qr(): void
    {
        $this->recepcion('ordenes-servicio.store');
        $orden = $this->postJson('/api/ordenes-servicio', $this->payload())->json();

        $enlaces = $this->postJson("/api/ordenes-servicio/{$orden['id']}/impresion")
            ->assertOk()
            ->json();

        $this->get($enlaces['orden'])
            ->assertOk()
            ->assertSee($orden['codigo'])
            ->assertSee('Ana Quispe')
            ->assertSee('data:image/svg+xml;base64,', false)
            // La clave del equipo nunca sale impresa.
            ->assertDontSee('1234');

        $this->get($enlaces['etiqueta'])
            ->assertOk()
            ->assertSee($orden['codigo'])
            ->assertSee('IdeaPad 3');

        // El QR apunta a la página pública de seguimiento.
        $this->assertStringEndsWith('/seguimiento/'.$orden['token'], OrdenServicio::find($orden['id'])->urlSeguimiento());
    }

    public function test_sin_firma_o_con_firma_alterada_no_se_imprime(): void
    {
        $this->recepcion('ordenes-servicio.store');
        $id = $this->postJson('/api/ordenes-servicio', $this->payload())->json('id');
        $otra = $this->postJson('/api/ordenes-servicio', $this->payload())->json('id');

        $this->get("/imprimir/ordenes-servicio/{$id}/orden")->assertForbidden();

        // La firma de una orden no abre otra.
        $firmado = $this->postJson("/api/ordenes-servicio/{$id}/impresion")->json('orden');
        $this->get(str_replace("/{$id}/orden", "/{$otra}/orden", $firmado))->assertForbidden();
    }

    public function test_sin_permiso_no_obtiene_enlaces_de_impresion(): void
    {
        $this->recepcion('ordenes-servicio.store');
        $id = $this->postJson('/api/ordenes-servicio', $this->payload())->json('id');

        $this->recepcion('pedidos.index');
        $this->postJson("/api/ordenes-servicio/{$id}/impresion")->assertForbidden();
    }

    public function test_el_dashboard_trae_el_taller_las_ventas_por_mes_y_por_canal(): void
    {
        $user = $this->recepcion('ordenes-servicio.store', 'ordenes-servicio.index', 'ordenes-servicio.estado', 'reportes.ventas', 'pedidos.pagos');
        $this->abrirCaja($user);
        $orden = $this->postJson('/api/ordenes-servicio', $this->payload())->json();
        $this->postJson('/api/ordenes-servicio', $this->payload());
        $this->postJson("/api/pedidos/{$orden['pedido_id']}/pagos", ['pago' => ['metodo' => 'efectivo', 'monto' => 230]]);
        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'reparado']);
        $this->postJson("/api/ordenes-servicio/{$orden['id']}/estado", ['estado' => 'entregado'])->assertOk();

        $panel = $this->getJson('/api/panel/dashboard')->assertOk();

        $panel->assertJsonPath('taller.en_taller', 1)
            ->assertJsonPath('taller.recibidas_hoy', 2)
            ->assertJsonPath('taller.entregadas_mes', 1)
            ->assertJsonCount(12, 'ventas.por_mes');
        // La entrega confirmó el cobro: es una venta del canal "servicio".
        $this->assertSame('servicio', $panel->json('ventas.por_canal.0.canal'));
        $this->assertEquals(100, $panel->json('ventas.por_canal.0.porcentaje'));
        $this->assertEquals(230, $panel->json('ventas.por_mes.11.total'));
    }
}
