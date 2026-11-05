<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * La pantalla de backups: lista lo que hay en cada destino y deja bajarlo.
 * (Hacer uno necesita mysqldump: se prueba a mano, no acá.)
 */
class RespaldosTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private const ARCHIVO = '2026-11-05-03-00-00.zip';

    protected function setUp(): void
    {
        parent::setUp();

        config(['backup.backup.name' => 'nexo', 'backup.backup.destination.disks' => ['local']]);
        Storage::fake('local');
        Storage::disk('local')->put('nexo/'.self::ARCHIVO, 'zip de prueba');
    }

    public function test_lista_los_backups_de_cada_destino(): void
    {
        $this->actuarCon('respaldos.index');

        $this->getJson('/api/respaldos')
            ->assertOk()
            ->assertJsonPath('destinos.0.disco', 'local')
            ->assertJsonPath('destinos.0.en_la_nube', false)
            ->assertJsonPath('destinos.0.backups.0.archivo', self::ARCHIVO)
            ->assertJsonStructure(['ultimo', 'al_dia']);
    }

    public function test_baja_un_backup_de_la_lista_y_nada_mas(): void
    {
        $this->actuarCon('respaldos.descargar');

        $respuesta = $this->get('/api/respaldos/local/'.self::ARCHIVO)->assertOk();
        $this->assertSame('zip de prueba', $respuesta->streamedContent());

        $this->get('/api/respaldos/local/otro.zip')->assertNotFound();
    }

    public function test_sin_permiso_no_los_ve(): void
    {
        $this->actuarCon('configuracion.update');

        $this->getJson('/api/respaldos')->assertForbidden();
        $this->get('/api/respaldos/local/'.self::ARCHIVO)->assertForbidden();
    }
}
