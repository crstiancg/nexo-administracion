<?php

/*
|--------------------------------------------------------------------------
| Autorización por nombre de ruta
|--------------------------------------------------------------------------
|
| El permiso ES el nombre de la ruta (roles.index, usuarios.store...). El
| middleware `autorizar.ruta` rechaza todo lo que no esté permitido, así que
| una ruta nueva nace bloqueada. Los permisos se crean con
| `php artisan permisos:sync` a partir de las rutas; no se tipean a mano.
|
*/

return [

    // Usuario reservado (el del dueño del sistema): sólo él se ve a sí mismo
    // en Usuarios. Para cualquier otro no aparece en la lista ni existe al
    // abrirlo, editarlo o borrarlo (404). null = ninguno oculto.
    'usuario_oculto' => env('USUARIO_OCULTO_ID', 1),

    // Rutas que sólo exigen sesión (están en el grupo pero no piden permiso).
    'libres' => [
        'auth.user',
        'auth.logout',
        // Filtran adentro por permiso (App\Support\Permisos).
        'panel.dashboard',
        'panel.alertas',
        'panel.buscar',
    ],

    // Permisos que también habilitan otra ruta: "esta ruta se permite a
    // quien tenga CUALQUIERA de estos". Sirve para las pantallas que leen
    // catálogos de otro módulo sin dar acceso a administrarlo.
    'implicitos' => [
        // El form de usuarios lista roles y permisos para tildar.
        'roles.index' => ['usuarios.store', 'usuarios.update'],
        // El form de roles y el de usuarios listan permisos para tildar.
        'permisos.index' => ['roles.store', 'roles.update', 'usuarios.store', 'usuarios.update'],
        // El diálogo "Nuevo permiso" lista las rutas que se pueden elegir.
        'permisos.rutas-disponibles' => ['permisos.store'],
        // Editar implica poder leer el registro que se edita.
        'roles.show' => ['roles.update'],
        'permisos.show' => ['permisos.update'],
        'usuarios.show' => ['usuarios.update'],
        'colores.show' => ['colores.update'],
        'categorias.show' => ['categorias.update'],
        // El form de categorías lista las demás para elegir la categoría padre.
        'categorias.index' => ['categorias.store', 'categorias.update', 'productos.store', 'productos.update', 'productos.index', 'ventas.store', 'ofertas.store', 'ofertas.update'],
        'marcas.show' => ['marcas.update'],
        'sedes.show' => ['sedes.update'],
        // Elegir sede: al asignarla a un usuario, al cambiarse de sede, al
        // trasladar y en los filtros de inventario, pedidos y cajas.
        'sedes.index' => ['usuarios.store', 'usuarios.update', 'auth.cambiar-sede', 'inventario.traslados', 'inventario.index', 'inventario.lotes', 'pedidos.index', 'cajas.index', 'productos.index', 'productos.store', 'productos.update'],
        'unidades.show' => ['unidades.update'],
        // Quien ve la lista abre la ficha del producto (stock, lotes, movimientos).
        'productos.show' => ['productos.update', 'productos.index'],
        'ofertas.show' => ['ofertas.update'],
        // El form de ofertas busca el producto al que se aplica.
        'productos.index' => ['ofertas.store', 'ofertas.update'],
        // El form de productos elige la marca, y la unidad y el color de
        // cada presentación; marca y color también filtran el punto de venta.
        'marcas.index' => ['productos.store', 'productos.update', 'productos.index', 'ventas.store', 'inventario.index'],
        'unidades.index' => ['productos.store', 'productos.update'],
        'colores.index' => ['productos.store', 'productos.update', 'ventas.store'],
        'ventas.catalogo' => ['ventas.store'],
        // La ficha de sólo lectura del POS (el vendedor no administra productos).
        'ventas.ficha' => ['ventas.store'],
        // Quien vende o cobra emite el comprobante y lo reenvía si no llegó.
        'comprobantes.store' => ['ventas.store', 'pedidos.pagos'],
        'comprobantes.reenviar' => ['ventas.store', 'pedidos.pagos'],
        'comprobantes.xml' => ['comprobantes.index', 'pedidos.index'],
        'comprobantes.cdr' => ['comprobantes.index', 'pedidos.index'],
        // Los formularios de inventario buscan la variante de cada línea.
        // Los formularios de pedidos buscan variantes y clientes.
        // La salida manual elige de qué lote sacar.
        'inventario.lotes' => ['inventario.salidas'],
        // Quien registra entradas completa lo que le faltó a una (costo,
        // factura, lote) y le da lote al stock que quedó sin lote.
        'inventario.corregir' => ['inventario.entradas'],
        'inventario.asignar-lote' => ['inventario.entradas'],
        'clientes.index' => ['pedidos.store', 'pedidos.update', 'ventas.store', 'cotizaciones.store', 'cotizaciones.update'],
        'clientes.show' => ['clientes.update'],
        'proveedores.show' => ['proveedores.update'],
        // El form de compras elige el proveedor.
        'proveedores.index' => ['compras.store', 'compras.index', 'ordenes-compra.store', 'ordenes-compra.update', 'ordenes-compra.index'],
        'proveedores.consultar-ruc' => ['proveedores.store', 'proveedores.update'],
        // Quien ve la lista abre el detalle (como pedidos).
        'compras.show' => ['compras.index', 'compras.anular'],
        // Quien ve la lista abre el detalle (y lo imprime para el proveedor).
        'ordenes-compra.show' => ['ordenes-compra.index', 'ordenes-compra.update', 'ordenes-compra.enviar', 'ordenes-compra.anular'],
        'cotizaciones.show' => ['cotizaciones.index', 'cotizaciones.update'],
        // Quien ve una cotización (o la lista de productos) la baja en Excel.
        'cotizaciones.excel' => ['cotizaciones.index', 'cotizaciones.show'],
        'productos.exportar' => ['productos.index'],
        // El form de cotizaciones busca clientes y presentaciones.
        'inventario.variantes' => ['inventario.entradas', 'inventario.salidas', 'inventario.ajustes', 'inventario.traslados', 'pedidos.store', 'pedidos.update', 'ventas.store', 'compras.store', 'cotizaciones.store', 'cotizaciones.update', 'ordenes-compra.store', 'ordenes-compra.update', 'inventario.index'],
        // Autocompletar con RENIEC/SUNAT es parte de cargar un cliente.
        'clientes.consultar-documento' => ['clientes.store', 'clientes.update'],
        // A diferencia de los catálogos, quien ve la lista de pedidos puede
        // abrir su detalle (un vendedor necesita ver qué lleva cada pedido),
        // y el punto de venta lo lee para reimprimir el ticket.
        'pedidos.show' => ['pedidos.index', 'pedidos.update', 'ventas.store'],
        // Cobrar, devolver, mover caja o vender necesita saber si hay una abierta.
        'cajas.actual' => ['pedidos.pagos', 'pedidos.devoluciones', 'cajas.abrir', 'cajas.cerrar', 'cajas.movimientos', 'cajas.index', 'ventas.store'],
        'cajas.show' => ['cajas.index', 'cajas.cerrar'],
    ],

    // Para la descripción que genera permisos:sync: "Roles · Crear".
    'recursos' => [
        'auth' => 'Sesión',
        'sedes' => 'Sedes',
        'roles' => 'Roles',
        'permisos' => 'Permisos',
        'usuarios' => 'Usuarios',
        'colores' => 'Colores',
        'categorias' => 'Categorías',
        'marcas' => 'Marcas',
        'unidades' => 'Unidades de medida',
        'productos' => 'Productos',
        'ofertas' => 'Ofertas',
        'inventario' => 'Inventario',
        'clientes' => 'Clientes',
        'proveedores' => 'Proveedores',
        'compras' => 'Compras',
        'ordenes-compra' => 'Pedidos de compra',
        'cotizaciones' => 'Cotizaciones',
        'reportes' => 'Reportes',
        'pedidos' => 'Pedidos',
        'cajas' => 'Caja',
        'ventas' => 'Punto de venta',
        'comprobantes' => 'Comprobantes electrónicos',
        'etiquetas' => 'Etiquetas',
    ],

    'acciones' => [
        'index' => 'Ver listado',
        'show' => 'Ver detalle',
        'store' => 'Crear',
        'update' => 'Editar',
        'destroy' => 'Eliminar',
        'toggle-active' => 'Dar de baja / activar',
        'sesiones' => 'Ver sesiones',
        'sesiones.revocar' => 'Cerrar sesiones',
        'rutas-disponibles' => 'Ver rutas sin permiso',
        'entradas' => 'Registrar entradas',
        'salidas' => 'Registrar salidas',
        'ajustes' => 'Ajustar por conteo',
        'traslados' => 'Trasladar entre sedes',
        'lotes' => 'Ver lotes y vencimientos',
        'corregir' => 'Completar datos de una entrada',
        'asignar-lote' => 'Asignar lote al stock sin lote',
        'cambiar-sede' => 'Cambiar de sede',
        'variantes' => 'Buscar presentaciones',
        'consultar-documento' => 'Consultar DNI/RUC',
        'confirmar' => 'Confirmar (descuenta stock)',
        'entregar' => 'Marcar entregado',
        'cancelar' => 'Cancelar',
        'pagos' => 'Cobrar',
        'devoluciones' => 'Devolver pagos',
        'actual' => 'Ver caja abierta',
        'abrir' => 'Abrir caja',
        'cerrar' => 'Cerrar caja (arqueo)',
        'movimientos' => 'Registrar ingresos y egresos',
        'catalogo' => 'Ver catálogo',
        'ficha' => 'Ver ficha de producto',
        'reenviar' => 'Reenviar a SUNAT',
        'xml' => 'Descargar XML',
        'cdr' => 'Descargar CDR (respuesta de SUNAT)',
        'imprimir' => 'Imprimir códigos de barras',
        'consultar-ruc' => 'Consultar RUC',
        'anular' => 'Anular',
        'enviar' => 'Marcar enviado al proveedor',
        'excel' => 'Descargar en Excel',
        'exportar' => 'Exportar a Excel',
        'convertir' => 'Convertir en pedido',
        'rechazar' => 'Rechazar',
        'ventas' => 'Ver ventas',
        'productos' => 'Ver más vendidos',
        'inventario' => 'Ver inventario valorizado',
    ],

];
