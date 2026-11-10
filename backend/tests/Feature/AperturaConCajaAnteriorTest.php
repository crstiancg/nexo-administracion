<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Sede;
use App\Models\User;
use App\Services\Cajas;
use App\Support\Fechas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class AperturaConCajaAnteriorTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private function a(string $fechaHora): void
    {
        Carbon::setTestNow(Carbon::parse($fechaHora, Fechas::zona()));
    }

    /** Caja del 10/11 con S/ 50 de apertura y S/ 100 de ingreso: esperado S/ 150. */
    private function cajaDeAyerSinCerrar(): User
    {
        $this->a('2026-11-10 09:00');
        $user = $this->actuarCon('cajas.actual', 'cajas.abrir');
        $user->forceFill(['sede_id' => Sede::create(['nombre' => 'Matriz'])->id])->save();
        app(Cajas::class)->abrir($user->sede_id, 50, $user);
        app(Cajas::class)->movimiento(MovimientoCaja::INGRESO, 100, 'Cambio', $user);

        return $user;
    }

    public function test_propone_lo_que_quedo_y_al_abrir_completa_el_arqueo_de_la_anterior(): void
    {
        $this->cajaDeAyerSinCerrar();
        $this->a('2026-11-11 08:00');

        // Al consultar, la de ayer se cierra sola y se propone su efectivo.
        $this->getJson('/api/cajas/actual')
            ->assertOk()
            ->assertJsonPath('caja', null)
            ->assertJsonPath('anterior.monto_esperado', '150.00');

        $this->postJson('/api/cajas/abrir', ['caja' => ['monto_apertura' => 140]])->assertCreated();

        $ayer = Caja::query()->orderBy('id')->firstOrFail();
        $this->assertSame('140.00', (string) $ayer->monto_contado);
        $this->assertSame('-10.00', (string) $ayer->diferencia);
        $this->assertStringContainsString('Arqueo al abrir la caja siguiente', $ayer->observacion_cierre);

        // Con arqueo ya no se vuelve a proponer.
        $this->assertNull(app(Cajas::class)->anteriorSinArqueo($ayer->sede_id));
    }

    public function test_si_la_anterior_se_cerro_a_mano_se_abre_normal(): void
    {
        $user = $this->cajaDeAyerSinCerrar();
        $caja = app(Cajas::class)->actual($user->sede_id);
        app(Cajas::class)->cerrar($caja, 150, null, $user);
        $this->a('2026-11-11 08:00');

        $this->getJson('/api/cajas/actual')->assertJsonPath('anterior', null);
        $this->postJson('/api/cajas/abrir', ['caja' => ['monto_apertura' => 0]])->assertCreated();

        $this->assertSame('150.00', (string) $caja->fresh()->monto_contado);
    }
}
