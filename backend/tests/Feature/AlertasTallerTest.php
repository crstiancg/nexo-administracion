<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\Sede;
use App\Models\TipoEquipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class AlertasTallerTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private function orden(Sede $sede, string $estado, array $cambios = []): OrdenServicio
    {
        $orden = new OrdenServicio([
            'cliente_id' => Cliente::create(['nombre' => 'Ana'])->id,
            'tipo_equipo_id' => TipoEquipo::firstOrCreate(['nombre' => 'Laptop'], ['activo' => true])->id,
            'falla' => 'No enciende',
        ]);
        $orden->forceFill(array_merge([
            'sede_id' => $sede->id,
            'estado' => $estado,
            'token' => Str::random(32),
            'recibido_at' => now(),
        ], $cambios))->save();

        return $orden;
    }

    public function test_avisa_listos_sin_recoger_atrasados_y_sin_tecnico(): void
    {
        $sede = Sede::create(['nombre' => 'Matriz']);
        $user = $this->actuarCon('ordenes-servicio.index');
        $user->forceFill(['sede_id' => $sede->id])->save();

        // Listo hace 5 días (sin recoger) y otro listo hoy (todavía no avisa).
        $viejo = $this->orden($sede, OrdenServicio::REPARADO, ['tecnico_id' => $user->id]);
        OrdenServicio::query()->whereKey($viejo->id)->update(['updated_at' => now()->subDays(5)]);
        $this->orden($sede, OrdenServicio::REPARADO, ['tecnico_id' => $user->id]);
        // Recibido hace 10 días, sin técnico.
        $this->orden($sede, OrdenServicio::DIAGNOSTICO, ['recibido_at' => now()->subDays(10)]);

        $alertas = collect($this->getJson('/api/panel/alertas')->assertOk()->json('data'))->keyBy('clave');

        $this->assertSame(1, $alertas['taller_sin_recoger']['cantidad']);
        $this->assertSame('/ordenes-servicio?estado=reparado', $alertas['taller_sin_recoger']['to']);
        $this->assertSame(1, $alertas['taller_atrasados']['cantidad']);
        $this->assertSame(1, $alertas['taller_sin_tecnico']['cantidad']);
    }

    public function test_sin_permiso_del_taller_no_ve_esos_avisos(): void
    {
        $sede = Sede::create(['nombre' => 'Matriz']);
        $user = $this->actuarCon('pedidos.index');
        $user->forceFill(['sede_id' => $sede->id])->save();
        $this->orden($sede, OrdenServicio::DIAGNOSTICO, ['recibido_at' => now()->subDays(10)]);

        $claves = collect($this->getJson('/api/panel/alertas')->json('data'))->pluck('clave');
        $this->assertFalse($claves->contains('taller_atrasados'));
    }
}
