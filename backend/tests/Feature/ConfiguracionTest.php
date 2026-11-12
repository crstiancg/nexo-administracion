<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class ConfiguracionTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** @return array<string, mixed> */
    private function datos(array $empresa = [], array $whatsapp = []): array
    {
        return [
            'empresa' => array_merge([
                'nombre' => 'NEXO TECHNOLOGY S.R.L.',
                'ruc' => '20610596780',
                'direccion' => 'Jr. Arequipa 418 - Puno',
                'telefono' => '900 551 900',
                'pie_ticket' => 'Gracias por elegirnos',
            ], $empresa),
            'whatsapp' => $whatsapp,
        ];
    }

    public function test_cualquiera_con_sesion_lee_la_configuracion_con_valores_por_defecto(): void
    {
        $this->actuarCon();

        $this->getJson('/api/configuracion')
            ->assertOk()
            ->assertJsonPath('nombre', config('empresa.nombre'))
            ->assertJsonPath('logo_url', null)
            ->assertJsonStructure(['nombre', 'ruc', 'direccion', 'telefono', 'lema', 'garantia', 'pie_ticket', 'terminos_orden', 'whatsapp']);
    }

    public function test_guarda_los_datos_y_las_plantillas_de_whatsapp(): void
    {
        $this->actuarCon('configuracion.update');

        $this->putJson('/api/configuracion', $this->datos(
            ['direccion' => 'Av. El Sol 100 - Puno'],
            ['reparado' => 'Hola {cliente}, tu {equipo} está listo.', 'inventado' => 'no se guarda'],
        ))->assertOk()
            ->assertJsonPath('direccion', 'Av. El Sol 100 - Puno')
            ->assertJsonPath('pie_ticket', 'Gracias por elegirnos')
            ->assertJsonPath('whatsapp.reparado', 'Hola {cliente}, tu {equipo} está listo.')
            ->assertJsonMissingPath('whatsapp.inventado');

        // Un campo vacío vuelve al valor por defecto.
        $this->putJson('/api/configuracion', $this->datos(['lema' => '']))
            ->assertOk()
            ->assertJsonPath('lema', config('empresa.lema'));
    }

    public function test_valida_el_ruc_y_la_razon_social(): void
    {
        $this->actuarCon('configuracion.update');

        $this->putJson('/api/configuracion', $this->datos(['nombre' => '', 'ruc' => '123']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['empresa.nombre', 'empresa.ruc']);
    }

    public function test_sube_y_quita_el_logo(): void
    {
        Storage::fake('public');
        $this->actuarCon('configuracion.update');

        $url = $this->post('/api/configuracion/logo', ['logo' => UploadedFile::fake()->image('logo.png', 400, 200)], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('logo_url');
        $this->assertNotNull($url);
        $this->assertCount(1, Storage::disk('public')->files('empresa'));

        $this->deleteJson('/api/configuracion/logo')->assertOk()->assertJsonPath('logo_url', null);
        $this->assertCount(0, Storage::disk('public')->files('empresa'));
    }

    public function test_guarda_la_tabla_de_precios_de_referencia(): void
    {
        $this->actuarCon('configuracion.update');

        // Sin configurar: vacía (no se sugiere nada).
        $this->getJson('/api/configuracion')->assertOk()->assertJsonPath('margenes', []);

        $this->putJson('/api/configuracion', [...$this->datos(), 'margenes' => [
            ['hasta' => 50, 'porcentaje' => 40],
            ['hasta' => 200.5, 'porcentaje' => 30],
            ['hasta' => null, 'porcentaje' => 20],
        ]])->assertOk()
            ->assertJsonPath('margenes.0.hasta', 50)
            ->assertJsonPath('margenes.1.porcentaje', 30)
            ->assertJsonPath('margenes.2.hasta', null);

        // Guardar sin mandar la tabla no la borra.
        $this->putJson('/api/configuracion', $this->datos())->assertOk()->assertJsonCount(3, 'margenes');

        // Y la ve cualquiera con sesión (el form de producto la usa).
        $this->actuarCon('productos.update');
        $this->getJson('/api/configuracion')->assertJsonPath('margenes.0.porcentaje', 40);
    }

    public function test_los_tramos_van_en_orden_y_solo_el_ultimo_sin_tope(): void
    {
        $this->actuarCon('configuracion.update');

        $this->putJson('/api/configuracion', [...$this->datos(), 'margenes' => [
            ['hasta' => 200, 'porcentaje' => 30],
            ['hasta' => 50, 'porcentaje' => 40],
        ]])->assertUnprocessable()->assertJsonValidationErrors('margenes');

        $this->putJson('/api/configuracion', [...$this->datos(), 'margenes' => [
            ['hasta' => null, 'porcentaje' => 30],
            ['hasta' => 50, 'porcentaje' => 40],
        ]])->assertUnprocessable()->assertJsonValidationErrors('margenes');

        $this->putJson('/api/configuracion', [...$this->datos(), 'margenes' => [
            ['hasta' => 50, 'porcentaje' => -5],
        ]])->assertUnprocessable()->assertJsonValidationErrors('margenes.0.porcentaje');
    }

    public function test_sin_permiso_no_cambia_la_configuracion(): void
    {
        $this->actuarCon('pedidos.index');

        $this->putJson('/api/configuracion', $this->datos())->assertForbidden();
        $this->deleteJson('/api/configuracion/logo')->assertForbidden();
    }
}
