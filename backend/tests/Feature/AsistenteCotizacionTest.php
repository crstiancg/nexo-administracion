<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\Stock;
use App\Models\Unidad;
use App\Models\Variante;
use App\Services\AsistenteCotizacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActuaConPermisos;
use Tests\TestCase;

/**
 * El bucle del asistente sin llamar a la API: un "modelo" de mentira que
 * primero busca y después propone, para probar las herramientas y el armado
 * del borrador.
 */
class AsistenteCotizacionTest extends TestCase
{
    use ActuaConPermisos;
    use RefreshDatabase;

    private Variante $disco;

    private Cliente $cliente;

    /** @var array<int, array<int, array<string, mixed>>> lo que el asistente le mandó al "modelo" */
    private array $enviados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $sede = Sede::create(['nombre' => 'Matriz']);
        $this->cliente = Cliente::create(['nombre' => 'Juan Pérez', 'numero_documento' => '40123456']);
        $producto = Producto::create([
            'nombre' => 'Disco SSD 480 GB',
            'categoria_id' => Categoria::create(['nombre' => 'Almacenamiento'])->id,
            'marca_id' => Marca::create(['nombre' => 'Kingston', 'activo' => true])->id,
            'precio' => 150,
        ]);
        $this->disco = $producto->variantes()->create([
            'presentacion' => 'Unidad',
            'unidad_id' => Unidad::create(['nombre' => 'Unidad', 'abreviatura' => 'und'])->id,
            'sku' => 'SSD-480',
        ]);
        (new Stock)->forceFill(['sede_id' => $sede->id, 'variante_id' => $this->disco->id, 'cantidad' => 3, 'activo' => true])->save();

        $this->actuarCon('cotizaciones.store')->forceFill(['sede_id' => $sede->id])->save();
    }

    /** @param  array<int, object>  $respuestas  una por vuelta */
    private function conRespuestas(array $respuestas): void
    {
        $test = $this;
        $this->app->instance(AsistenteCotizacion::class, new class($respuestas, $test) extends AsistenteCotizacion
        {
            public function __construct(private array $respuestas, private $test) {}

            protected function llamar(array $messages): object
            {
                $this->test->registrar($messages);

                return array_shift($this->respuestas);
            }
        });
    }

    public function registrar(array $messages): void
    {
        $this->enviados[] = $messages;
    }

    private static function herramienta(string $id, string $nombre, array $input): object
    {
        return (object) ['type' => 'tool_use', 'id' => $id, 'name' => $nombre, 'input' => $input];
    }

    public function test_busca_en_el_catalogo_y_devuelve_el_borrador_listo_para_el_form(): void
    {
        $this->conRespuestas([
            (object) ['stopReason' => 'tool_use', 'content' => [
                self::herramienta('t1', 'buscar_clientes', ['texto' => 'Juan']),
                self::herramienta('t2', 'buscar_productos', ['texto' => 'ssd 480']),
            ]],
            (object) ['stopReason' => 'tool_use', 'content' => [
                self::herramienta('t3', 'proponer_cotizacion', [
                    'cliente_id' => $this->cliente->id,
                    'tipo' => 'pc',
                    'garantia' => '1 año',
                    'forma_pago' => null,
                    'entrega' => null,
                    'notas' => 'Falta el precio del procesador.',
                    'items' => [
                        ['variante_id' => $this->disco->id, 'descripcion' => null, 'componente' => 'Disco sólido SSD', 'cantidad' => 1, 'precio_unitario' => 150],
                        ['variante_id' => null, 'descripcion' => 'Intel Core i5-14400', 'componente' => 'Procesador', 'cantidad' => 1, 'precio_unitario' => 0],
                        // Un id inventado sin descripción se descarta.
                        ['variante_id' => 999999, 'descripcion' => null, 'componente' => 'Mouse', 'cantidad' => 1, 'precio_unitario' => 20],
                    ],
                ]),
            ]],
        ]);

        $this->postJson('/api/cotizaciones/asistente', ['texto' => 'PC con SSD 480 para Juan Pérez, garantía 1 año'])
            ->assertOk()
            ->assertJsonPath('cliente.id', $this->cliente->id)
            ->assertJsonPath('cotizacion.tipo', 'pc')
            ->assertJsonPath('cotizacion.garantia', '1 año')
            ->assertJsonCount(2, 'cotizacion.items')
            ->assertJsonPath('cotizacion.items.0.variante.sku', 'SSD-480')
            ->assertJsonPath('cotizacion.items.0.componente', 'Disco sólido SSD')
            ->assertJsonPath('cotizacion.items.1.variante', null)
            ->assertJsonPath('cotizacion.items.1.descripcion', 'Intel Core i5-14400')
            ->assertJsonPath('notas', 'Falta el precio del procesador.');

        // Las herramientas encontraron al cliente y el disco en la sede.
        $resultados = collect($this->enviados[1][2]['content'])->keyBy('toolUseID');
        $this->assertStringContainsString('Juan Pérez', $resultados['t1']['content']);
        $this->assertStringContainsString('SSD-480', $resultados['t2']['content']);
    }

    public function test_si_el_modelo_pide_un_dato_se_lo_muestra_al_vendedor(): void
    {
        $this->conRespuestas([
            (object) ['stopReason' => 'end_turn', 'content' => [
                (object) ['type' => 'text', 'text' => '¿Para qué uso es la PC: oficina o gaming?'],
            ]],
        ]);

        $this->postJson('/api/cotizaciones/asistente', ['texto' => 'una pc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['texto' => '¿Para qué uso es la PC: oficina o gaming?']);
    }

    public function test_sin_clave_avisa_que_no_esta_configurado(): void
    {
        config(['services.anthropic.key' => null]);

        $this->postJson('/api/cotizaciones/asistente', ['texto' => 'mouse para Juan'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('texto');
    }
}
