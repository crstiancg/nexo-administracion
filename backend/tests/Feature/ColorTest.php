<?php

namespace Tests\Feature;

use App\Models\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class ColorTest extends TestCase
{
    use ActuaConPermisos, RefreshDatabase;

    private function todos(): void
    {
        $this->actuarCon('colores.index', 'colores.show', 'colores.store', 'colores.update', 'colores.destroy');
    }

    public function test_sin_permiso_devuelve_403(): void
    {
        $this->actuarCon();

        $this->getJson('/api/colores')->assertForbidden();
    }

    public function test_index_busca_por_nombre_o_hexadecimal(): void
    {
        $this->todos();
        Color::create(['nombre' => 'Rojo marca', 'hexadecimal' => '#E30613']);
        Color::create(['nombre' => 'Azul', 'hexadecimal' => '#1E40AF']);

        $this->getJson('/api/colores?rowsPerPage=10&search=e306')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.nombre', 'Rojo marca');
    }

    public function test_store_normaliza_el_hexadecimal_a_mayusculas_con_numeral(): void
    {
        $this->todos();

        $this->postJson('/api/colores', ['color' => ['nombre' => 'Verde', 'hexadecimal' => '22c55e']])
            ->assertCreated()
            ->assertJsonPath('hexadecimal', '#22C55E');
    }

    public function test_store_valida_formato_y_unicidad(): void
    {
        $this->todos();
        Color::create(['nombre' => 'Rojo', 'hexadecimal' => '#FF0000']);

        $this->postJson('/api/colores', ['color' => ['nombre' => '', 'hexadecimal' => '#GG0000']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color.nombre', 'color.hexadecimal']);

        // Mismo color escrito distinto (minúsculas, sin #) sigue siendo repetido.
        $this->postJson('/api/colores', ['color' => ['nombre' => 'rojo', 'hexadecimal' => 'ff0000']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color.nombre', 'color.hexadecimal']);
    }

    public function test_store_acepta_el_formato_corto_y_lo_expande(): void
    {
        $this->todos();

        $this->postJson('/api/colores', ['color' => ['nombre' => 'Blanco', 'hexadecimal' => '#fff']])
            ->assertCreated()
            ->assertJsonPath('hexadecimal', '#FFFFFF');
    }

    public function test_show_update_conservando_valores_y_destroy(): void
    {
        $this->todos();
        $color = Color::create(['nombre' => 'Rojo', 'hexadecimal' => '#FF0000']);

        $this->getJson("/api/colores/{$color->id}")->assertOk()->assertJsonPath('nombre', 'Rojo');

        $this->putJson("/api/colores/{$color->id}", ['color' => ['nombre' => 'Rojo', 'hexadecimal' => '#ff0000']])
            ->assertOk();

        $this->putJson("/api/colores/{$color->id}", ['color' => ['nombre' => 'Rojo fuego', 'hexadecimal' => '#CC0000']])
            ->assertOk()
            ->assertJsonPath('hexadecimal', '#CC0000');

        $this->deleteJson("/api/colores/{$color->id}")->assertNoContent();
        $this->assertDatabaseMissing('colores', ['id' => $color->id]);
    }
}
