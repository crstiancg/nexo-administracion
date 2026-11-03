<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\OfertaController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\UnidadController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VentaController;
use App\Http\Middleware\AutorizarPorRuta;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

// El login es POST /oauth/token (password grant), lo registra Passport.

// {usuario}: el usuario reservado (config permisos.usuario_oculto) sólo
// existe para él mismo; para cualquier otro, 404 (ver, editar, borrar…).
Route::bind('usuario', fn (string $valor) => User::query()
    ->visiblesPara(request()->user())
    ->findOrFail($valor));

// Autorización por nombre de ruta (config/permisos.php): el nombre de cada
// ruta ES el permiso que exige, y lo que no está permitido se rechaza. Toda
// ruta nueva de este grupo necesita nombre y después `php artisan permisos:sync`.
Route::middleware(['auth:api', AutorizarPorRuta::ALIAS])->group(function () {
    Route::get('/user', [AuthController::class, 'user'])->name('auth.user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::patch('/user/sede', [AuthController::class, 'cambiarSede'])->name('auth.cambiar-sede');

    // Dashboard, campana y búsqueda global: sólo piden sesión (permisos.libres);
    // cada bloque se filtra adentro según lo que el usuario puede ver.
    Route::get('panel/dashboard', [PanelController::class, 'dashboard'])->name('panel.dashboard');
    Route::get('panel/alertas', [PanelController::class, 'alertas'])->name('panel.alertas');
    Route::get('panel/buscar', [PanelController::class, 'buscar'])->name('panel.buscar');

    Route::middleware(HandlePrecognitiveRequests::class)->group(function () {
        Route::apiResource('roles', RolController::class);

        // Los permisos son rutas: se crean eligiendo entre las rutas sin
        // permiso (nunca tipeando el nombre) y no se borran por API.
        // rutas-disponibles va antes del resource: si no, {permiso} la captura.
        Route::get('permisos/rutas-disponibles', [PermisoController::class, 'rutasDisponibles'])
            ->name('permisos.rutas-disponibles');
        Route::apiResource('permisos', PermisoController::class)->only(['index', 'store', 'show', 'update']);

        Route::apiResource('usuarios', UserController::class);
        Route::apiResource('colores', ColorController::class)->parameters(['colores' => 'color']);
        Route::apiResource('categorias', CategoriaController::class);
        Route::apiResource('sedes', SedeController::class);
        Route::apiResource('marcas', MarcaController::class);
        Route::apiResource('unidades', UnidadController::class)->parameters(['unidades' => 'unidad']);
        // Va antes del resource: si no, {producto} captura "exportar".
        Route::get('productos/exportar', [ProductoController::class, 'exportar'])->name('productos.exportar');
        Route::apiResource('productos', ProductoController::class);
        Route::apiResource('ofertas', OfertaController::class);

        // Inventario: el libro sólo se consulta y se le agregan movimientos
        // (no hay update ni destroy: un error se corrige con otro movimiento).
        Route::post('inventario/entradas', [InventarioController::class, 'entradas'])->name('inventario.entradas');
        Route::post('inventario/salidas', [InventarioController::class, 'salidas'])->name('inventario.salidas');
        Route::post('inventario/ajustes', [InventarioController::class, 'ajustes'])->name('inventario.ajustes');
        Route::post('inventario/traslados', [InventarioController::class, 'traslados'])->name('inventario.traslados');
        Route::patch('inventario/movimientos/{movimiento}', [InventarioController::class, 'corregir'])->name('inventario.corregir');
        Route::post('inventario/lotes/asignar', [InventarioController::class, 'asignarLote'])->name('inventario.asignar-lote');

        // Va antes del resource: si no, {cliente} captura "consultar-documento".
        Route::get('clientes/consultar-documento', [ClienteController::class, 'consultarDocumento'])
            ->name('clientes.consultar-documento');
        Route::apiResource('clientes', ClienteController::class);

        // Va antes del resource: si no, {proveedor} captura "consultar-ruc".
        Route::get('proveedores/consultar-ruc', [ProveedorController::class, 'consultarRuc'])
            ->name('proveedores.consultar-ruc');
        Route::apiResource('proveedores', ProveedorController::class)->parameters(['proveedores' => 'proveedor']);

        // Una compra no se edita ni se borra: se anula.
        Route::apiResource('compras', CompraController::class)->only(['index', 'store', 'show']);

        // Sin destroy: se anula o se recibe con una compra.
        Route::apiResource('ordenes-compra', OrdenCompraController::class)
            ->parameters(['ordenes-compra' => 'orden'])
            ->except(['destroy']);

        // Sin destroy: se rechaza o se convierte en pedido.
        Route::apiResource('cotizaciones', CotizacionController::class)
            ->parameters(['cotizaciones' => 'cotizacion'])
            ->except(['destroy']);

        // Sin destroy: un pedido es historial de ventas, se cancela.
        Route::apiResource('pedidos', PedidoController::class)->except(['destroy']);

        // Dinero: siempre contra la caja abierta. Pagos y movimientos no se
        // editan ni se borran (un error se corrige con una devolución).
        Route::post('pedidos/{pedido}/pagos', [PagoController::class, 'cobrar'])->name('pedidos.pagos');
        Route::post('pedidos/{pedido}/devoluciones', [PagoController::class, 'devolver'])->name('pedidos.devoluciones');
        Route::post('cajas/abrir', [CajaController::class, 'abrir'])->name('cajas.abrir');
        Route::post('cajas/movimientos', [CajaController::class, 'movimientos'])->name('cajas.movimientos');
        Route::post('cajas/{caja}/cerrar', [CajaController::class, 'cerrar'])->name('cajas.cerrar');
    });

    // Punto de venta: pedido + confirmación + cobro + entrega en una sola
    // transacción (reusa los servicios de pedidos y caja).
    Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::get('ventas/catalogo', [VentaController::class, 'catalogo'])->name('ventas.catalogo');
    Route::get('ventas/catalogo/{producto}', [VentaController::class, 'ficha'])->name('ventas.ficha');

    // Facturación electrónica (SUNAT): boletas y facturas de los pedidos.
    Route::get('comprobantes', [ComprobanteController::class, 'index'])->name('comprobantes.index');
    Route::post('pedidos/{pedido}/comprobantes', [ComprobanteController::class, 'store'])->name('comprobantes.store');
    Route::post('comprobantes/{comprobante}/reenviar', [ComprobanteController::class, 'reenviar'])->name('comprobantes.reenviar');
    Route::get('comprobantes/{comprobante}/xml', [ComprobanteController::class, 'xml'])->name('comprobantes.xml');
    Route::get('comprobantes/{comprobante}/cdr', [ComprobanteController::class, 'cdr'])->name('comprobantes.cdr');

    // "actual" va antes de {caja}: si no, la captura como id.
    Route::get('cajas/actual', [CajaController::class, 'actual'])->name('cajas.actual');
    Route::get('cajas', [CajaController::class, 'index'])->name('cajas.index');
    Route::get('cajas/{caja}', [CajaController::class, 'show'])->name('cajas.show');

    Route::post('compras/{compra}/anular', [CompraController::class, 'anular'])->name('compras.anular');
    Route::post('ordenes-compra/{orden}/enviar', [OrdenCompraController::class, 'enviar'])->name('ordenes-compra.enviar');
    Route::post('ordenes-compra/{orden}/anular', [OrdenCompraController::class, 'anular'])->name('ordenes-compra.anular');
    Route::get('cotizaciones/{cotizacion}/excel', [CotizacionController::class, 'excel'])->name('cotizaciones.excel');
    Route::post('cotizaciones/{cotizacion}/convertir', [CotizacionController::class, 'convertir'])->name('cotizaciones.convertir');
    Route::post('cotizaciones/{cotizacion}/rechazar', [CotizacionController::class, 'rechazar'])->name('cotizaciones.rechazar');

    Route::get('reportes/ventas', [ReporteController::class, 'ventas'])->name('reportes.ventas');
    Route::get('reportes/productos', [ReporteController::class, 'productos'])->name('reportes.productos');
    Route::get('reportes/inventario', [ReporteController::class, 'inventario'])->name('reportes.inventario');

    Route::post('pedidos/{pedido}/confirmar', [PedidoController::class, 'confirmar'])->name('pedidos.confirmar');
    Route::post('pedidos/{pedido}/entregar', [PedidoController::class, 'entregar'])->name('pedidos.entregar');
    Route::post('pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar'])->name('pedidos.cancelar');
    Route::post('pedidos/{pedido}/anular', [PedidoController::class, 'anular'])->name('pedidos.anular');

    Route::get('inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('inventario/variantes', [InventarioController::class, 'variantes'])->name('inventario.variantes');
    Route::get('inventario/lotes', [InventarioController::class, 'lotes'])->name('inventario.lotes');

    // Las etiquetas se arman e imprimen en el navegador: acá sólo se buscan.
    Route::get('etiquetas', [EtiquetaController::class, 'index'])->name('etiquetas.imprimir');

    Route::patch('usuarios/{usuario}/toggle-active', [UserController::class, 'toggleActive'])
        ->name('usuarios.toggle-active');
    Route::get('usuarios/{usuario}/sesiones', [UserController::class, 'sesiones'])
        ->name('usuarios.sesiones');
    Route::delete('usuarios/{usuario}/sesiones/{token}', [UserController::class, 'revocarSesion'])
        ->name('usuarios.sesiones.revocar');
});
