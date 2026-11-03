<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\Cotizacion;
use App\Models\Marca;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sede;
use App\Models\Stock;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Variante;
use App\Services\Cajas;
use App\Services\Compras;
use App\Services\Cotizaciones;
use App\Services\Inventario;
use App\Services\Pedidos;
use App\Support\Ean13;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Datos de DEMOSTRACIÓN para probar el sistema de punta a punta: sedes,
 * catálogo de materiales, proveedores, clientes, compras con lotes,
 * traslados, ventas de los últimos días y cotizaciones.
 *
 * Todo pasa por los mismos servicios que usa la app (Compras, Inventario,
 * Pedidos, Cajas, Cotizaciones): el stock, el kardex, los lotes, la caja y
 * los reportes quedan consistentes, como si se hubiera cargado a mano.
 *
 * Sólo corre con la base sin productos (no pisa datos reales). Los RUC y
 * DNI son inventados.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function __construct(
        private Compras $compras,
        private Inventario $inventario,
        private Pedidos $pedidos,
        private Cajas $cajas,
        private Cotizaciones $cotizaciones,
    ) {}

    public function run(): void
    {
        if (Producto::query()->exists()) {
            $this->command?->warn('DemoSeeder: ya hay productos cargados, no se siembran datos de demostración.');

            return;
        }

        // `db:seed` desactiva el guard de asignación masiva, y los servicios
        // hacen fill() con los datos validados (que traen `items`): se
        // reactiva para que se comporten como en la app.
        $estabaSinGuard = Model::isUnguarded();
        Model::reguard();

        try {
            $this->sembrar();
        } finally {
            if ($estabaSinGuard) {
                Model::unguard();
            }
        }

        $this->command?->info('DemoSeeder: datos de demostración listos (usuario vendedor / vendedor123 en Arequipa).');
    }

    private function sembrar(): void
    {
        DB::transaction(function () {
            [$lima, $arequipa] = $this->sedes();
            [$admin, $vendedor] = $this->usuarios($lima, $arequipa);
            $v = $this->catalogo($lima, $arequipa);
            [$sika, $distribuidora, $ferreteria] = $this->proveedores();
            $clientes = $this->clientes();

            $this->comprasIniciales($admin, $vendedor, $v, $sika, $distribuidora, $ferreteria);
            $this->loteVencido($lima, $admin, $v);
            $this->trasladoALaOtraSede($admin, $arequipa, $v);
            $this->ventasDeLosUltimosDias($admin, $v, $clientes);
            $this->cotizacionesDeEjemplo($admin, $v, $clientes);
        });
    }

    /**
     * @return array{0: Sede, 1: Sede}
     */
    private function sedes(): array
    {
        $lima = Sede::query()->orderBy('id')->first();
        $lima->update(['nombre' => 'Lima - Surquillo', 'direccion' => 'Av. Principal 123, Surquillo', 'telefono' => '01 555 0101']);

        $arequipa = Sede::firstOrCreate(
            ['nombre' => 'Arequipa'],
            ['direccion' => 'Av. Ejército 456, Yanahuara', 'telefono' => '054 555 0202', 'activo' => true],
        );

        return [$lima, $arequipa];
    }

    /**
     * Un rol "Vendedor" de mostrador (sin catálogos ni administración) y un
     * usuario de Arequipa para probar el trabajo por sede.
     *
     * @return array{0: User, 1: User}
     */
    private function usuarios(Sede $lima, Sede $arequipa): array
    {
        $vendedorRol = Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'api']);
        $vendedorRol->syncPermissions(Permission::query()->where('guard_name', 'api')->whereIn('name', [
            'ventas.store', 'ventas.catalogo',
            'pedidos.index', 'pedidos.store', 'pedidos.update', 'pedidos.confirmar', 'pedidos.entregar', 'pedidos.cancelar', 'pedidos.pagos',
            'clientes.index', 'clientes.store', 'clientes.update', 'clientes.consultar-documento',
            'cotizaciones.index', 'cotizaciones.store', 'cotizaciones.update', 'cotizaciones.convertir', 'cotizaciones.rechazar',
            'cajas.actual', 'cajas.abrir', 'cajas.cerrar', 'cajas.movimientos',
            'inventario.index', 'inventario.lotes', 'productos.index',
        ])->get());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $admin->update(['sede_id' => $lima->id]);

        $vendedor = User::firstOrCreate(
            ['username' => 'vendedor'],
            ['name' => 'Rosa Quispe', 'password' => 'vendedor123', 'active' => true, 'sede_id' => $arequipa->id],
        );
        $vendedor->syncRoles([$vendedorRol]);

        return [$admin->refresh(), $vendedor];
    }

    /**
     * Productos de materiales de construcción con sus presentaciones, y qué
     * vende cada sede: Lima todo; Arequipa casi todo, con mínimos más chicos
     * y algunos precios propios (el flete encarece).
     *
     * @return array<string, Variante> por SKU
     */
    private function catalogo(Sede $lima, Sede $arequipa): array
    {
        // Lo que Arequipa no vende.
        $soloLima = ['Sika MonoTop 612', 'Chema Techo', 'Chema Sello Acrílico', 'Anclaje químico epóxico'];
        // Precio propio en Arequipa, por SKU.
        $preciosArequipa = ['SK1-GL' => 34.00, 'SK1-BLD20' => 142.00, 'SKCERAM-25' => 33.50, 'SKGROUT-30' => 61.00];

        $marcas = collect(['Sika', 'Chema', 'Z Aditivos', 'Celima'])
            ->mapWithKeys(fn ($nombre) => [$nombre => Marca::firstOrCreate(['nombre' => $nombre], ['activo' => true])]);

        $unidad = fn (string $abrev) => Unidad::query()->where('abreviatura', $abrev)->value('id');
        $categoria = fn (string $nombre) => Categoria::query()->where('nombre', $nombre)->value('id');

        $gris = Color::firstOrCreate(['nombre' => 'Gris'], ['hexadecimal' => '#8A8D8F']);
        $blanco = Color::firstOrCreate(['nombre' => 'Blanco'], ['hexadecimal' => '#FFFFFF']);
        $negro = Color::firstOrCreate(['nombre' => 'Negro'], ['hexadecimal' => '#1F1F1F']);

        // [nombre, marca, categoría, precio base, maneja lotes, descripción, [[presentación, unidad, sku, precio|null, mínimo, color|null]]]
        $productos = [
            ['Sikaflex 1A Plus', 'Sika', 'Poliuretanos', 38.50, false, 'Sellador elástico de poliuretano para juntas de construcción.', [
                ['Cartucho 300 ml', 'CRT', 'SKF1A-300-GRI', null, 24, $gris],
                ['Cartucho 300 ml', 'CRT', 'SKF1A-300-BLA', null, 24, $blanco],
                ['Salchicha 600 ml', 'UND', 'SKF1A-600-GRI', 62.00, 6, $gris],
            ]],
            ['Sikasil Universal', 'Sika', 'Siliconas', 24.90, false, 'Silicona acética multiuso.', [
                ['Cartucho 280 ml', 'CRT', 'SKSIL-280-BLA', null, 12, $blanco],
                ['Cartucho 280 ml', 'CRT', 'SKSIL-280-NEG', null, 6, $negro],
            ]],
            ['Sika-1', 'Sika', 'Para mezclas de concreto', 32.00, true, 'Impermeabilizante integral para morteros y concretos.', [
                ['Galón 4 L', 'GL', 'SK1-GL', null, 10, null],
                ['Balde 20 L', 'BLD', 'SK1-BLD20', 135.00, 4, null],
            ]],
            ['SikaFill Power 12', 'Sika', 'Techos y cubiertas', 95.00, true, 'Impermeabilizante elastomérico para techos, 12 años.', [
                ['Galón 4 kg', 'GL', 'SKFILL12-GL', null, 6, null],
                ['Balde 20 kg', 'BLD', 'SKFILL12-BLD', 410.00, 2, null],
            ]],
            ['Sikadur-32 Gel', 'Sika', 'Puentes de adherencia', 78.00, true, 'Adhesivo epóxico para unir concreto viejo con nuevo.', [
                ['Kit 1 kg', 'KIT', 'SKDUR32-1K', null, 5, null],
            ]],
            ['SikaGrout 212', 'Sika', 'Grouting y nivelación de maquinarias', 58.00, true, 'Mortero fluido de nivelación sin contracción.', [
                ['Bolsa 30 kg', 'BLS', 'SKGROUT-30', null, 15, null],
            ]],
            ['Sika Ceram Porcelanato', 'Sika', 'Pegamentos para Cerámicos', 32.00, true, 'Pegamento flexible para porcelanato y cerámico.', [
                ['Bolsa 25 kg', 'BLS', 'SKCERAM-25', null, 30, null],
            ]],
            ['Sika Cem Acelerante', 'Sika', 'Aditivos para Concreto', 18.50, true, 'Acelerante de fragua para concreto y mortero.', [
                ['Litro', 'L', 'SKCEM-ACEL-L', null, 10, null],
                ['Galón 4 L', 'GL', 'SKCEM-ACEL-GL', 62.00, 4, null],
            ]],
            ['Sika MonoTop 612', 'Sika', 'Morteros de reparación', 89.00, true, 'Mortero de reparación estructural.', [
                ['Bolsa 25 kg', 'BLS', 'SKMONO612-25', null, 8, null],
            ]],
            ['Chema Techo', 'Chema', 'Techos y cubiertas', 72.00, true, 'Impermeabilizante acrílico para techos.', [
                ['Galón', 'GL', 'CHTECHO-GL', null, 6, null],
                ['Balde 5 gl', 'BLD', 'CHTECHO-BLD', 315.00, 2, null],
            ]],
            ['Chema Sello Acrílico', 'Chema', 'Acrílicos e imprimantes', 16.00, false, 'Sellador acrílico pintable para juntas interiores.', [
                ['Cartucho 300 ml', 'CRT', 'CHSELLO-300', null, 12, $blanco],
            ]],
            ['Z Fragua', 'Z Aditivos', 'Pegamentos para Cerámicos', 7.50, false, 'Fragua para juntas de cerámico.', [
                ['Bolsa 1 kg', 'BLS', 'ZFRAG-1K-GRI', null, 20, $gris],
                ['Bolsa 1 kg', 'BLS', 'ZFRAG-1K-BLA', null, 20, $blanco],
            ]],
            ['Malla de refuerzo fibra de vidrio', 'Z Aditivos', 'Techos y cubiertas', 4.80, false, 'Malla para refuerzo de impermeabilizantes, ancho 1 m.', [
                ['Por metro', 'M', 'ZMALLA-M', null, 50, null],
                ['Rollo 50 m', 'RLL', 'ZMALLA-R50', 210.00, 2, null],
            ]],
            ['Anclaje químico epóxico', 'Sika', 'Anclajes', 85.00, true, 'Adhesivo para anclaje de varillas y pernos.', [
                ['Cartucho 300 ml', 'CRT', 'SKANCLA-300', null, 6, null],
            ]],
        ];

        $variantes = [];
        foreach ($productos as [$nombre, $marca, $cat, $precio, $lotes, $descripcion, $presentaciones]) {
            $producto = Producto::create([
                'nombre' => $nombre,
                'marca_id' => $marcas[$marca]->id,
                'categoria_id' => $categoria($cat),
                'descripcion' => $descripcion,
                'precio' => $precio,
                'activo' => true,
                'maneja_lotes' => $lotes,
            ]);

            foreach ($presentaciones as [$presentacion, $abrev, $sku, $precioPropio, $minimo, $color]) {
                $variantes[$sku] = $producto->variantes()->create([
                    'presentacion' => $presentacion,
                    'unidad_id' => $unidad($abrev),
                    'color_id' => $color?->id,
                    'sku' => $sku,
                    'precio' => $precioPropio,
                ]);
                // Demostración: un EAN-13 interno (prefijo 20) en vez del de fábrica.
                $variantes[$sku]->forceFill(['codigo_barras' => Ean13::paraVariante($variantes[$sku]->id)])->save();

                $this->habilitar($variantes[$sku], $lima, $minimo);
                if (! in_array($nombre, $soloLima, true)) {
                    $this->habilitar($variantes[$sku], $arequipa, ceil($minimo / 2), $preciosArequipa[$sku] ?? null);
                }
            }
        }

        return $variantes;
    }

    /**
     * La presentación se vende en la sede (fila de `stocks` en 0, como la
     * deja el formulario de productos).
     */
    private function habilitar(Variante $variante, Sede $sede, float $minimo, ?float $precio = null): void
    {
        $stock = new Stock;
        $stock->forceFill([
            'variante_id' => $variante->id,
            'sede_id' => $sede->id,
            'cantidad' => 0,
            'activo' => true,
            'precio' => $precio,
            'stock_minimo' => $minimo,
        ])->save();
    }

    /**
     * @return array{0: Proveedor, 1: Proveedor, 2: Proveedor}
     */
    private function proveedores(): array
    {
        return [
            Proveedor::firstOrCreate(['ruc' => '20999000011'], [
                'razon_social' => 'Distribuidora Sika Andina S.A.C. (demo)', 'contacto' => 'Carlos Mendoza',
                'telefono' => '987 654 321', 'email' => 'ventas@sika-andina.test', 'direccion' => 'Av. Industrial 1500, Lima', 'activo' => true,
            ]),
            Proveedor::firstOrCreate(['ruc' => '20999000022'], [
                'razon_social' => 'Químicos para la Construcción del Sur E.I.R.L. (demo)', 'contacto' => 'Lucía Vargas',
                'telefono' => '954 111 222', 'email' => 'pedidos@quimicossur.test', 'direccion' => 'Parque Industrial, Arequipa', 'activo' => true,
            ]),
            Proveedor::firstOrCreate(['ruc' => '10999000033'], [
                'razon_social' => 'Ferretería Mayorista El Constructor (demo)', 'contacto' => 'Jorge Huamán',
                'telefono' => '966 333 444', 'activo' => true,
            ]),
        ];
    }

    /**
     * @return array<int, Cliente>
     */
    private function clientes(): array
    {
        $datos = [
            ['RUC', '20999100011', 'Constructora Los Andes S.A.C. (demo)', '944 100 200', 'compras@losandes.test', 'Av. Arequipa 2450, Lince'],
            ['RUC', '20999100022', 'Inmobiliaria Pacífico Sur S.A. (demo)', '955 300 400', 'logistica@pacificosur.test', 'Calle Las Begonias 415, San Isidro'],
            ['RUC', '10999100033', 'Pedro Ramírez - Maestro de obra (demo)', '977 500 600', null, null],
            ['DNI', '99900011', 'María Fernández Torres (demo)', '988 700 800', null, 'Jr. Los Pinos 230, Surquillo'],
        ];

        return array_map(fn ($d) => Cliente::firstOrCreate(
            ['tipo_documento' => $d[0], 'numero_documento' => $d[1]],
            ['nombre' => $d[2], 'telefono' => $d[3], 'email' => $d[4], 'direccion' => $d[5]],
        ), $datos);
    }

    /**
     * Stock inicial por compras (Lima la mayoría; Arequipa compra lo suyo).
     * Algunos lotes vencen pronto para ver las alertas.
     *
     * @param  array<string, Variante>  $v
     */
    private function comprasIniciales(User $admin, User $vendedor, array $v, Proveedor $sika, Proveedor $sur, Proveedor $ferreteria): void
    {
        $vence = fn (int $dias) => today()->addDays($dias)->toDateString();
        $item = fn (string $sku, float $cantidad, float $costo, ?string $lote = null, ?int $dias = null) => array_filter([
            'variante_id' => $v[$sku]->id,
            'cantidad' => $cantidad,
            'costo_unitario' => $costo,
            'lote' => $lote,
            'vence_at' => $dias !== null ? $vence($dias) : null,
        ], fn ($x) => $x !== null);

        $this->compras->registrar([
            'proveedor_id' => $sika->id, 'tipo_documento' => 'factura', 'numero_documento' => 'F001-10234',
            'fecha' => today()->subDays(20)->toDateString(), 'observacion' => 'Pedido mensual de Sika.',
            'items' => [
                $item('SKF1A-300-GRI', 60, 26.00),
                $item('SKF1A-300-BLA', 40, 26.00),
                $item('SKF1A-600-GRI', 12, 43.00),
                $item('SKSIL-280-BLA', 30, 16.50),
                $item('SKSIL-280-NEG', 4, 16.50),
                $item('SK1-GL', 40, 21.00, 'S1-2409', 300),
                $item('SK1-BLD20', 8, 92.00, 'S1-2409', 300),
                $item('SKFILL12-GL', 20, 66.00, 'SF-2410', 400),
                $item('SKFILL12-BLD', 5, 290.00, 'SF-2410', 400),
                $item('SKDUR32-1K', 12, 52.00, 'D32-2405', 18),
                $item('SKGROUT-30', 50, 39.00, 'GR-2408', 120),
                $item('SKCERAM-25', 100, 21.00, 'CE-2409', 150),
                $item('SKCEM-ACEL-L', 25, 11.50, 'AC-2407', 25),
                $item('SKCEM-ACEL-GL', 6, 40.00, 'AC-2407', 25),
                $item('SKMONO612-25', 6, 61.00, 'MT-2409', 200),
                $item('SKANCLA-300', 15, 58.00, 'AN-2410', 365),
            ],
        ], $admin);

        $this->compras->registrar([
            'proveedor_id' => $ferreteria->id, 'tipo_documento' => 'boleta', 'numero_documento' => 'B002-5531',
            'fecha' => today()->subDays(12)->toDateString(),
            'items' => [
                $item('CHTECHO-GL', 15, 49.00, 'CH-0921', 240),
                $item('CHTECHO-BLD', 3, 220.00, 'CH-0921', 240),
                $item('CHSELLO-300', 30, 9.80),
                $item('ZFRAG-1K-GRI', 80, 4.20),
                $item('ZFRAG-1K-BLA', 60, 4.20),
                $item('ZMALLA-M', 200, 2.60),
                $item('ZMALLA-R50', 4, 120.00),
            ],
        ], $admin);

        // Arequipa compra a su proveedor local.
        $this->compras->registrar([
            'proveedor_id' => $sur->id, 'tipo_documento' => 'factura', 'numero_documento' => 'F003-881',
            'fecha' => today()->subDays(8)->toDateString(),
            'items' => [
                $item('SKCERAM-25', 40, 21.50, 'CE-2408A', 90),
                $item('SK1-GL', 12, 21.50, 'S1-2408A', 60),
                $item('ZFRAG-1K-GRI', 30, 4.40),
            ],
        ], $vendedor);
    }

    /**
     * Un lote de Sika-1 ya vencido en Lima (como si hubiera quedado de una
     * compra vieja): aparece en Vencimientos y las ventas no lo usan.
     *
     * @param  array<string, Variante>  $v
     */
    private function loteVencido(Sede $lima, User $admin, array $v): void
    {
        $this->inventario->entrada($lima->id, [[
            'variante_id' => $v['SK1-GL']->id,
            'cantidad' => 3,
            'costo_unitario' => 20.00,
            'lote' => 'S1-2312',
            'vence_at' => today()->subDays(15)->toDateString(),
        ]], 'Saldo inventario anterior', 'Carga de demostración', $admin);
    }

    /**
     * @param  array<string, Variante>  $v
     */
    private function trasladoALaOtraSede(User $admin, Sede $arequipa, array $v): void
    {
        $this->inventario->traslado($admin->sede_id, $arequipa->id, [
            ['variante_id' => $v['SKF1A-300-GRI']->id, 'cantidad' => 20],
            ['variante_id' => $v['SKSIL-280-BLA']->id, 'cantidad' => 10],
            ['variante_id' => $v['SKFILL12-GL']->id, 'cantidad' => 6],
            ['variante_id' => $v['SKGROUT-30']->id, 'cantidad' => 12],
            ['variante_id' => $v['ZMALLA-M']->id, 'cantidad' => 50],
        ], 'GR-T001-0045', 'Reposición semanal Arequipa', $admin);
    }

    /**
     * Ventas cobradas y entregadas de los últimos días en Lima, con su caja.
     * Se fechan hacia atrás para que los reportes por día tengan forma.
     *
     * @param  array<string, Variante>  $v
     * @param  array<int, Cliente>  $clientes
     */
    private function ventasDeLosUltimosDias(User $admin, array $v, array $clientes): void
    {
        $caja = $this->cajas->abrir($admin->sede_id, 200, $admin);
        $caja->forceFill(['abierta_at' => today()->subDays(6)->setTime(8, 30)])->save();

        $precio = fn (string $sku) => (float) ($v[$sku]->precio ?? $v[$sku]->producto->precio);

        // [días atrás, cliente|null, método, [[sku, cantidad]]]
        $ventas = [
            [6, null, 'efectivo', [['SKF1A-300-GRI', 3], ['ZFRAG-1K-GRI', 4]]],
            [6, $clientes[2], 'yape', [['SKCERAM-25', 10], ['ZFRAG-1K-BLA', 6]]],
            [5, $clientes[0], 'transferencia', [['SKGROUT-30', 12], ['SKDUR32-1K', 2], ['SK1-BLD20', 2]]],
            [4, null, 'efectivo', [['SKSIL-280-BLA', 2], ['CHSELLO-300', 3]]],
            [4, $clientes[3], 'tarjeta', [['CHTECHO-GL', 2], ['ZMALLA-M', 12.5]]],
            [3, null, 'efectivo', [['SK1-GL', 4], ['SKCEM-ACEL-L', 2.5]]],
            [2, $clientes[1], 'transferencia', [['SKFILL12-BLD', 2], ['ZMALLA-R50', 1], ['SKANCLA-300', 3]]],
            [2, null, 'plin', [['SKF1A-300-BLA', 6]]],
            [1, $clientes[2], 'efectivo', [['SKCERAM-25', 15], ['ZFRAG-1K-GRI', 10]]],
            [1, null, 'yape', [['SKSIL-280-NEG', 2], ['SKF1A-600-GRI', 1]]],
            [0, null, 'efectivo', [['SK1-GL', 2], ['ZFRAG-1K-BLA', 3]]],
            [0, $clientes[0], 'transferencia', [['SKMONO612-25', 4], ['SKDUR32-1K', 3]]],
        ];

        foreach ($ventas as $n => [$dias, $cliente, $metodo, $lineas]) {
            $pedido = $this->pedidos->guardar(new Pedido, [
                'cliente_id' => $cliente?->id,
                'canal' => $cliente ? 'whatsapp' : 'mostrador',
                'descuento' => 0,
                'observacion' => null,
                'items' => array_map(fn ($l) => [
                    'variante_id' => $v[$l[0]]->id,
                    'cantidad' => $l[1],
                    'precio_unitario' => $precio($l[0]),
                ], $lineas),
            ], $admin);

            $this->pedidos->confirmar($pedido, $admin);
            $pedido->refresh();
            $this->cajas->cobrar($pedido, [
                'metodo' => $metodo,
                'monto' => $pedido->total,
                'referencia' => $metodo === Pago::EFECTIVO ? null : 'OP'.(80000 + $n),
            ], $admin);
            $this->pedidos->entregar($pedido);

            // Hacia atrás en el tiempo, a distintas horas del día.
            $cuando = today()->subDays($dias)->setTime(9 + ($n % 8), 15 + ($n * 7) % 40);
            $this->fechar($pedido, $cuando);
        }

        // Un pedido por WhatsApp todavía pendiente (con adelanto) para el badge.
        $pendiente = $this->pedidos->guardar(new Pedido, [
            'cliente_id' => $clientes[1]->id,
            'canal' => 'whatsapp',
            'descuento' => 0,
            'observacion' => 'Entregar en obra, Calle Las Begonias. Llamar antes.',
            'items' => [
                ['variante_id' => $v['SKCERAM-25']->id, 'cantidad' => 20, 'precio_unitario' => 30.50],
                ['variante_id' => $v['ZFRAG-1K-GRI']->id, 'cantidad' => 10, 'precio_unitario' => 7.50],
            ],
        ], $admin);
        $this->cajas->cobrar($pendiente, ['metodo' => 'transferencia', 'monto' => 300, 'referencia' => 'OP89001'], $admin);
    }

    /**
     * Mueve las fechas de una venta (y de sus pagos y movimientos) a `$cuando`.
     */
    private function fechar(Pedido $pedido, Carbon $cuando): void
    {
        DB::table('pedidos')->where('id', $pedido->id)->update([
            'created_at' => $cuando, 'updated_at' => $cuando,
            'confirmado_at' => $cuando, 'entregado_at' => $cuando->copy()->addMinutes(2),
        ]);
        DB::table('pagos')->where('pedido_id', $pedido->id)->update(['created_at' => $cuando->copy()->addMinute()]);
        DB::table('movimiento_inventarios')->where('pedido_id', $pedido->id)->update(['created_at' => $cuando]);
    }

    /**
     * Una pendiente, una vencida y una convertida en pedido.
     *
     * @param  array<string, Variante>  $v
     * @param  array<int, Cliente>  $clientes
     */
    private function cotizacionesDeEjemplo(User $admin, array $v, array $clientes): void
    {
        $condiciones = "Pago: 50% de adelanto y saldo contra entrega.\nEntrega en obra en 48 h (Lima Metropolitana).\nPrecios incluyen IGV.";

        $this->cotizaciones->guardar(new Cotizacion, [
            'cliente_id' => $clientes[0]->id,
            'valida_hasta' => today()->addDays(12)->toDateString(),
            'condiciones' => $condiciones,
            'descuento' => 50,
            'items' => [
                ['variante_id' => $v['SKFILL12-BLD']->id, 'cantidad' => 6, 'precio_unitario' => 395.00],
                ['variante_id' => $v['ZMALLA-R50']->id, 'cantidad' => 3, 'precio_unitario' => 200.00],
                ['variante_id' => $v['SKF1A-300-GRI']->id, 'cantidad' => 24, 'precio_unitario' => 36.00],
            ],
        ], $admin);

        $vencida = $this->cotizaciones->guardar(new Cotizacion, [
            'cliente_id' => $clientes[1]->id,
            'valida_hasta' => today()->addDays(15)->toDateString(),
            'condiciones' => $condiciones,
            'descuento' => 0,
            'items' => [
                ['variante_id' => $v['SKCERAM-25']->id, 'cantidad' => 80, 'precio_unitario' => 30.00],
                ['variante_id' => $v['ZFRAG-1K-BLA']->id, 'cantidad' => 40, 'precio_unitario' => 7.00],
            ],
        ], $admin);
        // Hecha hace un mes: ya venció.
        DB::table('cotizaciones')->where('id', $vencida->id)->update([
            'valida_hasta' => today()->subDays(10)->toDateString(),
            'created_at' => today()->subDays(25), 'updated_at' => today()->subDays(25),
        ]);

        $aceptada = $this->cotizaciones->guardar(new Cotizacion, [
            'cliente_id' => $clientes[2]->id,
            'valida_hasta' => today()->addDays(10)->toDateString(),
            'condiciones' => 'Pago al contado. Recojo en tienda.',
            'descuento' => 0,
            'items' => [
                ['variante_id' => $v['SKGROUT-30']->id, 'cantidad' => 8, 'precio_unitario' => 55.00],
            ],
        ], $admin);
        $this->cotizaciones->convertir($aceptada, $admin);
    }
}
