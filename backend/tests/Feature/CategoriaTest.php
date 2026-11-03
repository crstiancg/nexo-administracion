<?php

namespace Tests\Feature;

use App\Models\Categoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

class CategoriaTest extends TestCase
{
    use ActuaConPermisos, RefreshDatabase;

    private function todos(): void
    {
        $this->actuarCon('categorias.index', 'categorias.show', 'categorias.store', 'categorias.update', 'categorias.destroy');
    }

    private function crear(string $nombre, ?Categoria $padre = null): Categoria
    {
        return Categoria::create(['nombre' => $nombre, 'parent_id' => $padre?->id]);
    }

    public function test_sin_permiso_devuelve_403(): void
    {
        $this->actuarCon();

        $this->getJson('/api/categorias')->assertForbidden();
    }

    public function test_index_trae_el_padre_y_la_cantidad_de_hijos(): void
    {
        $this->todos();
        $ropa = $this->crear('Ropa');
        $this->crear('Polos', $ropa);
        $this->crear('Pantalones', $ropa);

        $this->getJson('/api/categorias?rowsPerPage=10&search=polo')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.nombre', 'Polos')
            ->assertJsonPath('data.0.padre.nombre', 'Ropa');

        $this->getJson('/api/categorias?rowsPerPage=0&order_by=nombre')
            ->assertOk()
            ->assertJsonPath('data.2.nombre', 'Ropa')
            ->assertJsonPath('data.2.padre', null)
            ->assertJsonPath('data.2.hijos_count', 2);
    }

    public function test_index_filtra_por_padre(): void
    {
        $this->todos();
        $ropa = $this->crear('Ropa');
        $this->crear('Polos', $ropa);
        $this->crear('Juguetes');

        $this->getJson("/api/categorias?rowsPerPage=10&parent_id={$ropa->id}")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.nombre', 'Polos');
    }

    public function test_store_crea_raiz_y_subcategoria(): void
    {
        $this->todos();

        $ropa = $this->postJson('/api/categorias', ['categoria' => ['nombre' => '  Ropa ', 'parent_id' => null]])
            ->assertCreated()
            ->assertJsonPath('nombre', 'Ropa')
            ->assertJsonPath('parent_id', null)
            ->json('id');

        $this->postJson('/api/categorias', ['categoria' => ['nombre' => 'Polos', 'parent_id' => $ropa]])
            ->assertCreated()
            ->assertJsonPath('parent_id', $ropa);
    }

    public function test_el_nombre_es_unico_entre_hermanos_sin_distinguir_mayusculas(): void
    {
        $this->todos();
        $ninos = $this->crear('Niños');
        $ninas = $this->crear('Niñas');
        $this->crear('Polos', $ninos);

        $this->postJson('/api/categorias', ['categoria' => ['nombre' => 'polos', 'parent_id' => $ninos->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['categoria.nombre']);

        $this->postJson('/api/categorias', ['categoria' => ['nombre' => 'niños', 'parent_id' => null]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['categoria.nombre']);

        // Mismo nombre bajo otro padre sí se puede: "Niñas › Polos".
        $this->postJson('/api/categorias', ['categoria' => ['nombre' => 'Polos', 'parent_id' => $ninas->id]])
            ->assertCreated();
    }

    public function test_store_valida_requeridos_y_padre_existente(): void
    {
        $this->todos();

        $this->postJson('/api/categorias', ['categoria' => ['nombre' => '', 'parent_id' => 999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['categoria.nombre', 'categoria.parent_id']);
    }

    public function test_update_no_permite_ciclos(): void
    {
        $this->todos();
        $ropa = $this->crear('Ropa');
        $ninos = $this->crear('Niños', $ropa);
        $polos = $this->crear('Polos', $ninos);

        // Padre de sí misma.
        $this->putJson("/api/categorias/{$ropa->id}", ['categoria' => ['nombre' => 'Ropa', 'parent_id' => $ropa->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['categoria.parent_id']);

        // Debajo de un nieto: Ropa › Niños › Polos › Ropa.
        $this->putJson("/api/categorias/{$ropa->id}", ['categoria' => ['nombre' => 'Ropa', 'parent_id' => $polos->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['categoria.parent_id']);
    }

    public function test_show_update_conservando_el_nombre_y_moviendo_de_padre(): void
    {
        $this->todos();
        $ropa = $this->crear('Ropa');
        $juguetes = $this->crear('Juguetes');
        $polos = $this->crear('Polos', $ropa);

        $this->getJson("/api/categorias/{$polos->id}")
            ->assertOk()
            ->assertJsonPath('nombre', 'Polos')
            ->assertJsonPath('parent_id', $ropa->id);

        $this->putJson("/api/categorias/{$polos->id}", ['categoria' => ['nombre' => 'Polos', 'parent_id' => $ropa->id]])
            ->assertOk();

        $this->putJson("/api/categorias/{$polos->id}", ['categoria' => ['nombre' => 'Peluches', 'parent_id' => $juguetes->id]])
            ->assertOk()
            ->assertJsonPath('nombre', 'Peluches')
            ->assertJsonPath('parent_id', $juguetes->id);
    }

    public function test_destroy_no_borra_una_categoria_con_subcategorias(): void
    {
        $this->todos();
        $ropa = $this->crear('Ropa');
        $polos = $this->crear('Polos', $ropa);

        $this->deleteJson("/api/categorias/{$ropa->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'No se puede eliminar: tiene subcategorías. Elimínelas o muévalas primero.');
        $this->assertDatabaseHas('categorias', ['id' => $ropa->id]);

        $this->deleteJson("/api/categorias/{$polos->id}")->assertNoContent();
        $this->deleteJson("/api/categorias/{$ropa->id}")->assertNoContent();
        $this->assertDatabaseCount('categorias', 0);
    }
}
