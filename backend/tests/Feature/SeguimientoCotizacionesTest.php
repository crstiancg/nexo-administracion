<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\Sede;
use App\Models\User;
use App\Support\Fechas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * El dashboard muestra las cotizaciones por vencer y la conversión del mes.
 */
class SeguimientoCotizacionesTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Sede $sede;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sede = Sede::create(['nombre' => 'Matriz']);
        $this->vendedor = $this->actuarCon('cotizaciones.index');
        $this->vendedor->forceFill(['sede_id' => $this->sede->id, 'name' => 'Rosa'])->save();
    }

    private function cotizacion(string $estado, int $diasDeValidez, float $total): Cotizacion
    {
        static $n = 0;
        $n++;

        return (new Cotizacion)->forceFill([
            'codigo' => 'COT-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'token' => str_pad((string) $n, 32, 'x'),
            'estado' => $estado,
            'sede_id' => $this->sede->id,
            'user_id' => $this->vendedor->id,
            'valida_hasta' => Fechas::hoy()->addDays($diasDeValidez),
            'subtotal' => $total,
            'total' => $total,
        ]);
    }

    public function test_por_vencer_y_conversion_del_mes_por_vendedor(): void
    {
        $this->cotizacion(Cotizacion::PENDIENTE, 1, 900)->save();
        $this->cotizacion(Cotizacion::PENDIENTE, 10, 100)->save();
        $this->cotizacion(Cotizacion::CONVERTIDA, 5, 500)->save();
        $this->cotizacion(Cotizacion::RECHAZADA, 5, 50)->save();

        $this->getJson('/api/panel/dashboard')
            ->assertOk()
            ->assertJsonPath('cotizaciones.por_vencer_total', 1)
            ->assertJsonPath('cotizaciones.por_vencer.0.codigo', 'COT-000001')
            ->assertJsonPath('cotizaciones.mes.emitidas', 4)
            ->assertJsonPath('cotizaciones.mes.convertidas', 1)
            ->assertJsonPath('cotizaciones.mes.tasa', 25)
            ->assertJsonPath('cotizaciones.mes.monto', 500)
            ->assertJsonPath('cotizaciones.por_vendedor.0.vendedor', 'Rosa')
            ->assertJsonPath('cotizaciones.por_vendedor.0.tasa', 25);
    }

    public function test_sin_permiso_de_cotizaciones_no_viene(): void
    {
        $this->actuarCon('pedidos.index')->forceFill(['sede_id' => $this->sede->id])->save();

        $this->getJson('/api/panel/dashboard')->assertOk()->assertJsonPath('cotizaciones', null);
    }
}
