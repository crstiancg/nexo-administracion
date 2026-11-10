<?php

namespace Tests\Feature;

use App\Models\Sede;
use App\Models\User;
use App\Services\Cajas;
use App\Support\Fechas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class CierreCajaHorarioTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private function enLaSede(string ...$permisos): User
    {
        $user = $this->actuarCon(...$permisos);
        $user->forceFill(['sede_id' => Sede::create(['nombre' => 'Matriz'])->id])->save();

        return $user;
    }

    private function a(string $hora): void
    {
        Carbon::setTestNow(Carbon::parse("2026-11-10 {$hora}", Fechas::zona()));
    }

    /** @return array<string, mixed>|null */
    private function alertaCierre(): ?array
    {
        return collect($this->getJson('/api/panel/alertas')->assertOk()->json('data'))
            ->firstWhere('clave', 'caja_cierre');
    }

    public function test_avisa_desde_la_hora_de_aviso_y_es_urgente_pasada_la_hora_limite(): void
    {
        $this->a('09:00');
        $user = $this->enLaSede('cajas.actual', 'cajas.cerrar');
        app(Cajas::class)->abrir($user->sede_id, 50, $user);

        $this->a('18:59');
        $this->assertNull($this->alertaCierre());

        $this->a('19:00');
        $this->assertSame('warning', $this->alertaCierre()['nivel']);
        $this->assertStringContainsString('22:00', $this->alertaCierre()['detalle']);

        $this->a('22:00');
        $this->assertSame('critical', $this->alertaCierre()['nivel']);
        $this->assertSame('La caja debió cerrarse a las 22:00', $this->alertaCierre()['titulo']);
    }

    public function test_solo_avisa_a_quien_puede_cerrar_la_caja(): void
    {
        $this->a('09:00');
        $user = $this->enLaSede('cajas.actual');
        app(Cajas::class)->abrir($user->sede_id, 50, $user);

        $this->a('20:00');
        $this->assertNull($this->alertaCierre());
    }

    public function test_el_horario_se_cambia_en_configuracion(): void
    {
        $this->a('09:00');
        $user = $this->enLaSede('cajas.actual', 'cajas.cerrar', 'configuracion.update');
        app(Cajas::class)->abrir($user->sede_id, 50, $user);

        $this->putJson('/api/configuracion', [
            'empresa' => ['nombre' => 'Nexo'],
            'caja' => ['cierre_desde' => '21:00', 'cierre_hasta' => '20:00'],
        ])->assertUnprocessable()->assertJsonValidationErrors('caja.cierre_hasta');

        $this->putJson('/api/configuracion', [
            'empresa' => ['nombre' => 'Nexo'],
            'caja' => ['cierre_desde' => '20:30', 'cierre_hasta' => '21:30'],
        ])->assertOk()->assertJsonPath('caja.cierre_hasta', '21:30');

        $this->a('20:00');
        $this->assertNull($this->alertaCierre());
        $this->a('21:45');
        $this->assertSame('La caja debió cerrarse a las 21:30', $this->alertaCierre()['titulo']);
        // El punto de venta recibe el mismo horario con la caja.
        $this->getJson('/api/cajas/actual')->assertJsonPath('caja.cierre_desde', '20:30');
    }
}
