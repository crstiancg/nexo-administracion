<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\EstadoOrden;
use App\Models\OrdenServicio;
use App\Models\Sede;
use App\Models\TipoEquipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class EstadosOrdenTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $cambios = []): array
    {
        return ['estado' => array_merge([
            'nombre' => 'Enviado a laboratorio',
            'tipo' => EstadoOrden::EN_PROCESO,
            'icono' => 'science',
            'color' => 'info',
            'mensaje_cliente' => 'Su equipo está en un laboratorio especializado.',
            'notificar' => true,
            'activo' => true,
        ], $cambios)];
    }

    private function estado(string $clave): EstadoOrden
    {
        return EstadoOrden::query()->where('clave', $clave)->firstOrFail();
    }

    public function test_vienen_los_ocho_de_siempre_en_orden_con_su_tipo(): void
    {
        $this->actuarCon('estados-orden.index');

        $this->getJson('/api/estados-orden')
            ->assertOk()
            ->assertJsonCount(8)
            ->assertJsonPath('0.clave', 'recibido')
            ->assertJsonPath('0.tipo', EstadoOrden::INICIAL)
            ->assertJsonPath('0.de_sistema', true)
            ->assertJsonPath('0.notificar', false)
            ->assertJsonPath('4.notificar', true)
            ->assertJsonPath('6.clave', 'entregado')
            ->assertJsonPath('7.tipo', EstadoOrden::CERRADO);
    }

    public function test_crea_un_estado_con_clave_sacada_del_nombre_junto_a_los_de_su_tipo(): void
    {
        $this->actuarCon('estados-orden.store');

        // En proceso: después de "En reparación", antes de "Reparado".
        $this->postJson('/api/estados-orden', $this->payload())
            ->assertCreated()
            ->assertJsonPath('clave', 'enviado_a_labora')
            ->assertJsonPath('notificar', true)
            ->assertJsonPath('orden', 5);
        $this->assertSame(6, $this->estado('reparado')->orden);

        // Listo: antes de Entregado. Cerrado: al final, junto a Abandonado.
        $this->postJson('/api/estados-orden', $this->payload(['nombre' => 'Probado', 'tipo' => EstadoOrden::LISTO]))
            ->assertJsonPath('orden', 8);
        $this->assertSame(9, $this->estado('entregado')->orden);
        $this->postJson('/api/estados-orden', $this->payload(['nombre' => 'Donado', 'tipo' => EstadoOrden::CERRADO]))
            ->assertJsonPath('orden', 11);

        // Mismo nombre (sin importar mayúsculas): no.
        $this->postJson('/api/estados-orden', $this->payload(['nombre' => 'ENVIADO A LABORATORIO']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado.nombre');

        // No se crea otro inicial ni otro entregado.
        $this->postJson('/api/estados-orden', $this->payload(['nombre' => 'Otro', 'tipo' => EstadoOrden::ENTREGADO]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado.tipo');
    }

    public function test_los_de_sistema_se_renombran_pero_no_cambian_de_tipo_ni_se_apagan(): void
    {
        $this->actuarCon('estados-orden.update');
        $recibido = $this->estado('recibido');

        $this->putJson("/api/estados-orden/{$recibido->id}", $this->payload([
            'nombre' => 'Ingresado', 'tipo' => EstadoOrden::INICIAL, 'icono' => 'login',
        ]))->assertOk()->assertJsonPath('nombre', 'Ingresado');
        $this->assertSame('recibido', $recibido->fresh()->clave);

        $this->putJson("/api/estados-orden/{$recibido->id}", $this->payload([
            'nombre' => 'Ingresado', 'tipo' => EstadoOrden::EN_PROCESO, 'activo' => false,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['estado.tipo', 'estado.activo']);
    }

    public function test_solo_se_elimina_uno_que_nunca_se_uso(): void
    {
        $this->actuarCon('estados-orden.destroy');

        $this->deleteJson('/api/estados-orden/'.$this->estado('entregado')->id)->assertStatus(409);

        // Abandonado lo tiene una orden: se desactiva en su lugar.
        $orden = new OrdenServicio([
            'cliente_id' => Cliente::create(['nombre' => 'Ana'])->id,
            'tipo_equipo_id' => TipoEquipo::create(['nombre' => 'Laptop', 'activo' => true])->id,
            'falla' => 'No enciende',
        ]);
        $orden->forceFill([
            'sede_id' => Sede::create(['nombre' => 'Matriz'])->id,
            'estado' => 'abandonado',
            'token' => Str::random(32),
            'recibido_at' => now(),
        ])->save();
        $this->deleteJson('/api/estados-orden/'.$this->estado('abandonado')->id)->assertStatus(409);

        $this->deleteJson('/api/estados-orden/'.$this->estado('esperando_repuesto')->id)->assertNoContent();
        $this->assertDatabaseMissing('estados_orden', ['clave' => 'esperando_repuesto']);
    }

    public function test_reordena_la_lista(): void
    {
        $this->actuarCon('estados-orden.update');
        $ids = EstadoOrden::query()->orderBy('orden')->pluck('id')->all();

        $this->putJson('/api/estados-orden/ordenar', ['ids' => array_reverse($ids)])
            ->assertOk()
            ->assertJsonPath('0.clave', 'abandonado')
            ->assertJsonPath('7.clave', 'recibido');
    }

    public function test_configuracion_trae_los_estados_y_acepta_su_mensaje_de_whatsapp(): void
    {
        (new EstadoOrden(['nombre' => 'Probado', 'tipo' => EstadoOrden::LISTO, 'icono' => 'verified', 'color' => 'positive']))->forceFill(['clave' => 'probado', 'orden' => 9])->save();
        $this->actuarCon('configuracion.update');

        $this->getJson('/api/configuracion')
            ->assertOk()
            ->assertJsonCount(9, 'estados_orden')
            ->assertJsonPath('estados_orden.8.clave', 'probado');

        $this->putJson('/api/configuracion', [
            'empresa' => ['nombre' => 'Nexo'],
            'whatsapp' => ['probado' => 'Hola {cliente}, ya lo probamos.', 'inventado' => 'x'],
        ])->assertOk()
            ->assertJsonPath('whatsapp.probado', 'Hola {cliente}, ya lo probamos.')
            ->assertJsonMissingPath('whatsapp.inventado');
    }

    public function test_sin_permiso_no_los_administra(): void
    {
        $this->actuarCon('ordenes-servicio.index');

        $this->getJson('/api/estados-orden')->assertForbidden();
        $this->postJson('/api/estados-orden', $this->payload())->assertForbidden();
    }
}
