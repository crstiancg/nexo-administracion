<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * Lo sensible queda registrado: quién, qué cambió (antes → después) y cuándo.
 */
class AuditoriaTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = $this->actuarCon('auditoria.index');
    }

    private function producto(): Producto
    {
        return Producto::create([
            'nombre' => 'Laptop HP 250',
            'categoria_id' => Categoria::create(['nombre' => 'Laptops'])->id,
            'marca_id' => Marca::create(['nombre' => 'HP', 'activo' => true])->id,
            'precio' => 2500,
        ]);
    }

    public function test_registra_quien_cambio_el_precio_con_el_antes_y_el_despues(): void
    {
        // Otra petición: lo que sigue a un alta en la misma es parte del alta.
        $producto = $this->producto()->fresh();
        $producto->update(['precio' => 2300]);
        // El mismo valor con otro formato no es un cambio.
        $producto->update(['precio' => '2300.00']);

        $cambios = Auditoria::query()->where('modelo', 'Producto')->where('evento', 'editado')->get();
        $this->assertCount(1, $cambios);
        $this->assertSame($this->usuario->id, $cambios[0]->user_id);
        $this->assertSame('Laptop HP 250', $cambios[0]->etiqueta);
        $this->assertEquals([2500, 2300], array_map('floatval', $cambios[0]->cambios['precio']));

        $this->getJson('/api/auditoria?modelo=Producto&evento=editado')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.usuario.id', $this->usuario->id)
            ->assertJsonPath('data.0.modelo_label', 'Producto');
    }

    public function test_la_clave_de_un_usuario_no_queda_a_la_vista(): void
    {
        $this->usuario->update(['password' => 'otra-clave-secreta']);

        $registro = Auditoria::query()->where('modelo', 'User')->where('evento', 'editado')->sole();
        $this->assertSame(['••••', '••••'], $registro->cambios['password']);
        $this->assertStringNotContainsString('otra-clave', json_encode($registro->cambios));
    }

    public function test_sin_permiso_no_se_ve(): void
    {
        $this->actuarCon('productos.index');

        $this->getJson('/api/auditoria')->assertForbidden();
    }
}
