<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\Cotizacion;
use App\Models\Marca;
use App\Models\OrdenServicio;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sede;
use App\Models\Stock;
use App\Models\TipoEquipo;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Variante;
use App\Services\Cajas;
use App\Services\Compras;
use App\Services\Cotizaciones;
use App\Services\Inventario;
use App\Services\OrdenesServicio;
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
 * catálogo de tecnología, proveedores, clientes, compras, traslados, ventas
 * de los últimos días, cotizaciones y órdenes de servicio técnico.
 *
 * Todo pasa por los mismos servicios que usa la app (Compras, Inventario,
 * Pedidos, Cajas, Cotizaciones, OrdenesServicio): el stock, el kardex, la
 * caja y los reportes quedan consistentes, como si se hubiera cargado a mano.
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
        private OrdenesServicio $ordenes,
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

        $this->command?->info('DemoSeeder: datos de demostración listos (usuario vendedor / vendedor123 en Juliaca).');
    }

    private function sembrar(): void
    {
        DB::transaction(function () {
            [$matriz, $juliaca] = $this->sedes();
            [$admin, $vendedor] = $this->usuarios($matriz, $juliaca);
            $v = $this->catalogo($matriz, $juliaca);
            [$mayorista, $repuestos, $local] = $this->proveedores();
            $clientes = $this->clientes();

            $this->comprasIniciales($admin, $vendedor, $v, $mayorista, $repuestos, $local);
            $this->loteVencido($matriz, $admin, $v);
            $this->trasladoALaOtraSede($admin, $juliaca, $v);
            // La caja de hoy: cobra las ventas de demostración y los abonos
            // (si ya hay una abierta en la sede, se usa esa).
            if (! $this->cajas->actual($admin->sede_id)) {
                $this->cajas->abrir($admin->sede_id, 200, $admin);
            }
            $this->ventasDeLosUltimosDias($admin, $v, $clientes);
            $this->cotizacionesDeEjemplo($admin, $v, $clientes);
            $this->ordenesDeServicio($admin, $v, $clientes);
        });
    }

    /**
     * @return array{0: Sede, 1: Sede}
     */
    private function sedes(): array
    {
        $matriz = Sede::query()->orderBy('id')->first();
        $matriz->update(['nombre' => 'Puno - Matriz', 'direccion' => 'Jr. Arequipa 418, Puno', 'telefono' => '900 180 000']);

        $juliaca = Sede::firstOrCreate(
            ['nombre' => 'Juliaca'],
            ['direccion' => 'Jr. San Román 250, Juliaca', 'telefono' => '900 551 900', 'activo' => true],
        );

        return [$matriz, $juliaca];
    }

    /**
     * Un vendedor de Juliaca para probar el trabajo por sede (con el rol
     * Vendedor de PermissionSeeder).
     *
     * @return array{0: User, 1: User}
     */
    private function usuarios(Sede $matriz, Sede $juliaca): array
    {
        $vendedorRol = Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'api']);
        $vendedorRol->syncPermissions(Permission::query()->where('guard_name', 'api')->whereIn('name', PermissionSeeder::VENDEDOR)->get());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $admin->update(['sede_id' => $matriz->id]);

        $vendedor = User::firstOrCreate(
            ['username' => 'vendedor'],
            ['name' => 'Rosa Quispe', 'password' => 'vendedor123', 'active' => true, 'sede_id' => $juliaca->id],
        );
        $vendedor->syncRoles([$vendedorRol]);

        return [$admin->refresh(), $vendedor];
    }

    /**
     * Productos de una tienda de tecnología con sus presentaciones, y qué
     * vende cada sede: la matriz todo; Juliaca casi todo, con mínimos más
     * chicos y algunos precios propios.
     *
     * @return array<string, Variante> por SKU
     */
    private function catalogo(Sede $matriz, Sede $juliaca): array
    {
        // Lo que Juliaca no vende (repuestos que se instalan en el taller).
        $soloMatriz = ['Pantalla LED 15.6" slim 30 pines', 'Batería para laptop HP HT03XL'];
        // Precio propio en Juliaca, por SKU.
        $preciosJuliaca = ['KNG-A400-480' => 175.00, 'LOG-M170' => 42.00];

        $marca = fn (string $nombre) => Marca::firstOrCreate(['nombre' => $nombre], ['activo' => true])->id;
        $unidad = fn (string $abrev) => Unidad::query()->where('abreviatura', $abrev)->value('id');
        $categoria = fn (string $nombre) => Categoria::query()->where('nombre', $nombre)->value('id');

        $negro = Color::firstOrCreate(['nombre' => 'Negro'], ['hexadecimal' => '#1F1F1F']);
        $cian = Color::firstOrCreate(['nombre' => 'Cian'], ['hexadecimal' => '#00A3E0']);
        $magenta = Color::firstOrCreate(['nombre' => 'Magenta'], ['hexadecimal' => '#D6007E']);
        $amarillo = Color::firstOrCreate(['nombre' => 'Amarillo'], ['hexadecimal' => '#FFD100']);

        // [nombre, marca, categoría, precio base, maneja lotes, descripción, [[presentación, unidad, sku, precio|null, mínimo, color|null]]]
        $productos = [
            ['Mouse inalámbrico M170', 'Logitech', 'Mouse y teclados', 39.90, false, 'Mouse inalámbrico con receptor USB.', [
                ['Unidad', 'UND', 'LOG-M170', null, 10, $negro],
            ]],
            ['Teclado USB KB-110', 'Genius', 'Mouse y teclados', 29.90, false, 'Teclado USB en español.', [
                ['Unidad', 'UND', 'GEN-KB110', null, 8, $negro],
            ]],
            ['SSD A400', 'Kingston', 'Discos SSD', 119.00, false, 'Disco sólido SATA 2.5".', [
                ['240 GB', 'UND', 'KNG-A400-240', null, 6, null],
                ['480 GB', 'UND', 'KNG-A400-480', 169.00, 4, null],
            ]],
            ['Memoria USB DataTraveler', 'Kingston', 'Memorias USB y microSD', 25.00, false, 'USB 3.2.', [
                ['32 GB', 'UND', 'KNG-DT-32', null, 15, null],
                ['64 GB', 'UND', 'KNG-DT-64', 35.00, 10, null],
            ]],
            ['Memoria RAM DDR4 SODIMM 3200', 'Kingston', 'Memorias RAM', 129.00, false, 'Para laptop.', [
                ['8 GB', 'UND', 'KNG-SO8', null, 6, null],
                ['16 GB', 'UND', 'KNG-SO16', 229.00, 3, null],
            ]],
            ['Cargador USB-C 25W', 'Samsung', 'Cargadores', 69.00, false, 'Carga rápida, con cable.', [
                ['Unidad', 'UND', 'SAM-25W', null, 8, null],
            ]],
            ['Cable HDMI 2.0', 'Genérico', 'Cables y adaptadores', 15.00, false, 'Cable HDMI 4K.', [
                ['1.8 m', 'UND', 'HDMI-18', null, 10, null],
                ['10 m', 'UND', 'HDMI-10', 47.00, 3, null],
            ]],
            ['Cable UTP Cat 6', 'Genérico', 'Redes', 1.80, false, 'Por metro, para armar patch cords.', [
                ['Por metro', 'M', 'UTP6-M', null, 100, null],
                ['Caja 305 m', 'CJ', 'UTP6-305', 420.00, 1, null],
            ]],
            ['Tinta 544', 'Epson', 'Tintas', 39.00, true, 'Botella de 65 ml para EcoTank.', [
                ['Botella 65 ml', 'UND', 'EPS-544-BK', null, 10, $negro],
                ['Botella 65 ml', 'UND', 'EPS-544-C', null, 6, $cian],
                ['Botella 65 ml', 'UND', 'EPS-544-M', null, 6, $magenta],
                ['Botella 65 ml', 'UND', 'EPS-544-Y', null, 6, $amarillo],
            ]],
            ['Pasta térmica MX-4 4 g', 'Genérico', 'Pasta térmica y refrigeración', 35.00, false, 'Para procesadores y GPU.', [
                ['Unidad', 'UND', 'MX4-4G', null, 5, null],
            ]],
            ['Batería para laptop HP HT03XL', 'HP', 'Baterías', 159.00, false, 'Compatible con HP 240/250 G7.', [
                ['Unidad', 'UND', 'HP-HT03XL', null, 2, null],
            ]],
            ['Pantalla LED 15.6" slim 30 pines', 'Genérico', 'Pantallas', 289.00, false, 'Repuesto para laptop, HD.', [
                ['Unidad', 'UND', 'LED156-30P', null, 2, null],
            ]],
            ['Router Archer C6', 'TP-Link', 'Redes', 159.00, false, 'Router WiFi doble banda AC1200.', [
                ['Unidad', 'UND', 'TPL-C6', null, 3, null],
            ]],
        ];

        $variantes = [];
        foreach ($productos as [$nombre, $marcaNombre, $cat, $precio, $lotes, $descripcion, $presentaciones]) {
            $producto = Producto::create([
                'nombre' => $nombre,
                'marca_id' => $marca($marcaNombre),
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

                $this->habilitar($variantes[$sku], $matriz, $minimo);
                if (! in_array($nombre, $soloMatriz, true)) {
                    $this->habilitar($variantes[$sku], $juliaca, ceil($minimo / 2), $preciosJuliaca[$sku] ?? null);
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
                'razon_social' => 'Mayorista de Cómputo Andino S.A.C. (demo)', 'contacto' => 'Carlos Mendoza',
                'telefono' => '987 654 321', 'email' => 'ventas@computoandino.test', 'direccion' => 'Av. Garcilaso 1500, Lima', 'activo' => true,
            ]),
            Proveedor::firstOrCreate(['ruc' => '20999000022'], [
                'razon_social' => 'Repuestos Laptop del Sur E.I.R.L. (demo)', 'contacto' => 'Lucía Vargas',
                'telefono' => '954 111 222', 'email' => 'pedidos@repuestossur.test', 'direccion' => 'Calle Mercaderes 210, Arequipa', 'activo' => true,
            ]),
            Proveedor::firstOrCreate(['ruc' => '10999000033'], [
                'razon_social' => 'Importaciones Tecno Juliaca (demo)', 'contacto' => 'Jorge Huamán',
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
            ['RUC', '20999100011', 'Colegio Particular San Martín S.A.C. (demo)', '944 100 200', 'logistica@sanmartin.test', 'Av. El Sol 450, Puno'],
            ['RUC', '20999100022', 'Estudio Contable Quispe & Asociados (demo)', '955 300 400', 'administracion@quispe.test', 'Jr. Lima 315, Puno'],
            ['RUC', '10999100033', 'Juan Mamani - Técnico independiente (demo)', '977 500 600', null, null],
            ['DNI', '99900011', 'María Fernández Torres (demo)', '988 700 800', null, 'Jr. Los Incas 230, Puno'],
        ];

        return array_map(fn ($d) => Cliente::firstOrCreate(
            ['tipo_documento' => $d[0], 'numero_documento' => $d[1]],
            ['nombre' => $d[2], 'telefono' => $d[3], 'email' => $d[4], 'direccion' => $d[5]],
        ), $datos);
    }

    /**
     * Stock inicial por compras (la matriz la mayoría; Juliaca compra lo
     * suyo). Las tintas tienen lote y vencimiento para ver las alertas.
     *
     * @param  array<string, Variante>  $v
     */
    private function comprasIniciales(User $admin, User $vendedor, array $v, Proveedor $mayorista, Proveedor $repuestos, Proveedor $local): void
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
            'proveedor_id' => $mayorista->id, 'tipo_documento' => 'factura', 'numero_documento' => 'F001-10234',
            'fecha' => today()->subDays(20)->toDateString(), 'observacion' => 'Pedido mensual de accesorios.',
            'items' => [
                $item('LOG-M170', 30, 26.00),
                $item('GEN-KB110', 20, 18.00),
                $item('KNG-A400-240', 15, 82.00),
                $item('KNG-A400-480', 10, 125.00),
                $item('KNG-DT-32', 40, 15.00),
                $item('KNG-DT-64', 25, 22.00),
                $item('KNG-SO8', 12, 92.00),
                $item('KNG-SO16', 6, 170.00),
                $item('SAM-25W', 20, 42.00),
                $item('HDMI-18', 30, 7.50),
                $item('HDMI-10', 6, 28.00),
                $item('UTP6-305', 2, 300.00),
                $item('UTP6-M', 200, 1.00),
                $item('TPL-C6', 6, 112.00),
                $item('EPS-544-BK', 24, 26.00, 'EP-2601', 300),
                $item('EPS-544-C', 12, 26.00, 'EP-2601', 300),
                $item('EPS-544-M', 12, 26.00, 'EP-2601', 300),
                $item('EPS-544-Y', 12, 26.00, 'EP-2602', 25),
            ],
        ], $admin);

        $this->compras->registrar([
            'proveedor_id' => $repuestos->id, 'tipo_documento' => 'boleta', 'numero_documento' => 'B002-5531',
            'fecha' => today()->subDays(12)->toDateString(),
            'items' => [
                $item('HP-HT03XL', 4, 105.00),
                $item('LED156-30P', 4, 190.00),
                $item('MX4-4G', 10, 21.00),
            ],
        ], $admin);

        // Juliaca compra a su proveedor local.
        $this->compras->registrar([
            'proveedor_id' => $local->id, 'tipo_documento' => 'factura', 'numero_documento' => 'F003-881',
            'fecha' => today()->subDays(8)->toDateString(),
            'items' => [
                $item('LOG-M170', 10, 27.00),
                $item('KNG-DT-32', 15, 15.50),
                $item('HDMI-18', 10, 8.00),
            ],
        ], $vendedor);
    }

    /**
     * Un lote de tinta negra ya vencido en la matriz (quedó de una compra
     * vieja): aparece en Vencimientos y las ventas no lo usan.
     *
     * @param  array<string, Variante>  $v
     */
    private function loteVencido(Sede $matriz, User $admin, array $v): void
    {
        $this->inventario->entrada($matriz->id, [[
            'variante_id' => $v['EPS-544-BK']->id,
            'cantidad' => 3,
            'costo_unitario' => 24.00,
            'lote' => 'EP-2412',
            'vence_at' => today()->subDays(15)->toDateString(),
        ]], 'Saldo inventario anterior', 'Carga de demostración', $admin);
    }

    /**
     * @param  array<string, Variante>  $v
     */
    private function trasladoALaOtraSede(User $admin, Sede $juliaca, array $v): void
    {
        $this->inventario->traslado($admin->sede_id, $juliaca->id, [
            ['variante_id' => $v['KNG-A400-240']->id, 'cantidad' => 4],
            ['variante_id' => $v['SAM-25W']->id, 'cantidad' => 6],
            ['variante_id' => $v['EPS-544-BK']->id, 'cantidad' => 6],
            ['variante_id' => $v['UTP6-M']->id, 'cantidad' => 50],
        ], 'GR-T001-0045', 'Reposición semanal Juliaca', $admin);
    }

    /**
     * Ventas cobradas y entregadas de los últimos días en la matriz. Se
     * fechan hacia atrás para que los reportes por día tengan forma (la caja
     * es la de hoy: una de otro día ya no cobra).
     *
     * @param  array<string, Variante>  $v
     * @param  array<int, Cliente>  $clientes
     */
    private function ventasDeLosUltimosDias(User $admin, array $v, array $clientes): void
    {
        $precio = fn (string $sku) => (float) ($v[$sku]->precio ?? $v[$sku]->producto->precio);

        // [días atrás, cliente|null, método, [[sku, cantidad]]]
        $ventas = [
            [6, null, 'efectivo', [['LOG-M170', 1], ['KNG-DT-32', 2]]],
            [6, $clientes[2], 'yape', [['MX4-4G', 2], ['KNG-SO8', 1]]],
            [5, $clientes[0], 'transferencia', [['GEN-KB110', 10], ['LOG-M170', 10], ['HDMI-18', 5]]],
            [4, null, 'efectivo', [['SAM-25W', 1], ['HDMI-18', 1]]],
            [4, $clientes[3], 'tarjeta', [['EPS-544-BK', 2], ['EPS-544-C', 1], ['EPS-544-M', 1]]],
            [3, null, 'efectivo', [['UTP6-M', 15], ['KNG-DT-64', 1]]],
            [2, $clientes[1], 'transferencia', [['KNG-A400-480', 2], ['TPL-C6', 1]]],
            [2, null, 'plin', [['KNG-DT-32', 3]]],
            [1, $clientes[2], 'efectivo', [['KNG-SO16', 1], ['KNG-A400-240', 2]]],
            [1, null, 'yape', [['LOG-M170', 1], ['GEN-KB110', 1]]],
            [0, null, 'efectivo', [['HDMI-10', 1], ['SAM-25W', 1]]],
            [0, $clientes[0], 'transferencia', [['UTP6-305', 1], ['TPL-C6', 2]]],
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
            'observacion' => 'Entregar en la oficina del cliente. Llamar antes.',
            'items' => [
                ['variante_id' => $v['KNG-A400-480']->id, 'cantidad' => 3, 'precio_unitario' => 165.00],
                ['variante_id' => $v['KNG-SO8']->id, 'cantidad' => 3, 'precio_unitario' => 125.00],
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
        $condiciones = 'Incluye instalación y configuración.';
        $proforma = ['forma_pago' => 'Contado', 'entrega' => 'Con posibilidad de entrega en oficina o domicilio del cliente.'];

        $this->cotizaciones->guardar(new Cotizacion, [
            ...$proforma,
            'cliente_id' => $clientes[0]->id,
            'valida_hasta' => today()->addDays(7)->toDateString(),
            'condiciones' => $condiciones,
            'descuento' => 50,
            'items' => [
                ['variante_id' => $v['TPL-C6']->id, 'cantidad' => 3, 'precio_unitario' => 150.00],
                ['variante_id' => $v['UTP6-305']->id, 'cantidad' => 1, 'precio_unitario' => 410.00],
                ['variante_id' => $v['HDMI-10']->id, 'cantidad' => 4, 'precio_unitario' => 45.00],
            ],
        ], $admin);

        $vencida = $this->cotizaciones->guardar(new Cotizacion, [
            ...$proforma,
            'forma_pago' => '50% de adelanto y saldo contra entrega',
            'cliente_id' => $clientes[1]->id,
            'valida_hasta' => today()->addDays(7)->toDateString(),
            'condiciones' => null,
            'descuento' => 0,
            'items' => [
                ['variante_id' => $v['KNG-A400-480']->id, 'cantidad' => 5, 'precio_unitario' => 160.00],
                ['variante_id' => $v['KNG-SO16']->id, 'cantidad' => 5, 'precio_unitario' => 220.00],
            ],
        ], $admin);
        // Hecha hace un mes: ya venció.
        DB::table('cotizaciones')->where('id', $vencida->id)->update([
            'valida_hasta' => today()->subDays(10)->toDateString(),
            'created_at' => today()->subDays(25), 'updated_at' => today()->subDays(25),
        ]);

        $aceptada = $this->cotizaciones->guardar(new Cotizacion, [
            'cliente_id' => $clientes[2]->id,
            'valida_hasta' => today()->addDays(7)->toDateString(),
            'forma_pago' => 'Contado',
            'entrega' => 'Recojo en tienda.',
            'descuento' => 0,
            'items' => [
                ['variante_id' => $v['MX4-4G']->id, 'cantidad' => 4, 'precio_unitario' => 32.00],
            ],
        ], $admin);
        $this->cotizaciones->convertir($aceptada, $admin);
    }

    /**
     * Tres equipos en el taller: uno recién recibido, uno reparado esperando
     * que lo recojan (con repuesto y adelanto) y uno ya entregado.
     *
     * @param  array<string, Variante>  $v
     * @param  array<int, Cliente>  $clientes
     */
    private function ordenesDeServicio(User $admin, array $v, array $clientes): void
    {
        $tipo = fn (string $nombre) => TipoEquipo::query()->where('nombre', $nombre)->value('id');
        $marca = fn (string $nombre) => Marca::query()->where('nombre', $nombre)->value('id');
        $orden = fn (array $datos) => $this->ordenes->guardar(new OrdenServicio, array_merge([
            'tecnico_id' => $admin->id,
            'seguridad_tipo' => 'ninguno',
            'garantia_dias' => 30,
            'repuestos' => [],
        ], $datos), $admin);

        $orden([
            'cliente_id' => $clientes[3]->id,
            'tipo_equipo_id' => $tipo('Celular'),
            'marca_id' => $marca('Samsung'),
            'modelo' => 'Galaxy A14',
            'seguridad_tipo' => 'patron',
            'seguridad_valor' => '1-4-7-8-9',
            'checklist' => ['Enciende' => true, 'Pantalla sin daños' => false],
            'falla' => 'Pantalla rota, el táctil no responde en la parte de abajo.',
            'mano_obra' => 60,
        ]);

        $reparada = $orden([
            'cliente_id' => $clientes[1]->id,
            'tipo_equipo_id' => $tipo('Laptop'),
            'marca_id' => $marca('HP'),
            'modelo' => '250 G7',
            'serie' => 'CND9X1234',
            'accesorios' => 'Cargador',
            'checklist' => ['Enciende' => true, 'Cargador' => true, 'Batería' => false],
            'falla' => 'Muy lenta y la batería no carga.',
            'diagnostico' => 'Cambio de disco a SSD 480 GB, reinstalación de Windows y cambio de batería.',
            'mano_obra' => 80,
            'repuestos' => [
                ['variante_id' => $v['KNG-A400-480']->id, 'cantidad' => 1, 'precio_unitario' => 169.00],
                ['variante_id' => $v['HP-HT03XL']->id, 'cantidad' => 1, 'precio_unitario' => 159.00],
            ],
            'anticipo' => ['metodo' => 'yape', 'monto' => 200, 'referencia' => 'OP77001'],
        ]);
        $this->ordenes->cambiarEstado($reparada, OrdenServicio::DIAGNOSTICO, null, $admin);
        $this->ordenes->cambiarEstado($reparada, OrdenServicio::REPARADO, 'Probada 2 horas, todo OK.', $admin);

        $entregada = $orden([
            'cliente_id' => $clientes[0]->id,
            'tipo_equipo_id' => $tipo('Impresora'),
            'marca_id' => $marca('Epson'),
            'modelo' => 'L3250',
            'falla' => 'Imprime con rayas.',
            'diagnostico' => 'Limpieza de cabezal y purga de tinta.',
            'mano_obra' => 50,
        ]);
        $this->ordenes->cambiarEstado($entregada, OrdenServicio::REPARADO, null, $admin);
        $this->cajas->cobrar($entregada->pedido, ['metodo' => 'efectivo', 'monto' => 50], $admin);
        $this->ordenes->cambiarEstado($entregada, OrdenServicio::ENTREGADO, null, $admin);
    }
}
