# Referencia funcional: OrderFix (app.orderfixglobal.com)

Análisis de solo lectura del sistema OrderFix (versión 3.4.0), revisado el 02-11-2026 con la cuenta de Nexo Technology.
Sirve como referencia para el nuevo sistema **nexo-administracion**. No se copió ningún dato de clientes.

---

## 1. Visión general

- **Tipo:** SaaS multiempresa para talleres de reparación y tiendas.
- **Stack observado:** PHP 8.3 + Laravel, AdminLTE + Bootstrap 5, jQuery, DataTables. Es una PWA alojada en Hostinger.
- **Multiempresa:** cada empresa ve solo sus datos. La empresa contrata un plan con fecha de vencimiento ("56 días restantes").
- **Multisucursal:** hay un selector de sucursal en la barra superior. Los listados tienen el filtro "Esta sucursal / Todas mis sucursales". El stock y la caja van por sucursal.
- **Moneda y país configurables:** se usa S/ (PEN), con zona horaria America/Lima y una segunda moneda opcional con tasa de cambio.
- **Barra superior:** sucursal activa, accesos rápidos (Nueva orden, Nueva venta, Nueva cotización), buscador global (Ctrl+K), estado de la caja (abierta/cerrada), días restantes del plan, notificaciones, tema y perfil.

## 2. Mapa de módulos (menú lateral)

| Grupo | Opción | Ruta |
|---|---|---|
| Inicio | Dashboard | `/inicio` |
| **Órdenes** | Crear Orden | `/crear-orden` |
| | Orden Express | `/orden-express` |
| | Administrar Órdenes | `/ordenes` |
| | Estado de Reparaciones | `/estado` |
| | Consulta Status QR | `/status` |
| | Abonos | `/abonos-ordenes` |
| | Historial de Órdenes | `/historial-ordenes` |
| | Plantillas de Informes | `/informes-plantillas` |
| **Ventas** | Crear Venta (POS) | `/crear-venta` |
| | Venta Express | `/venta-express` |
| | Cotizaciones | `/cotizaciones` |
| | Administrar Ventas | `/ventas` |
| | Garantías | `/garantias` |
| | Crédito de Clientes | `/cobros-clientes` |
| | Mi Negocio Web (tienda online) | `/mi-tienda` |
| **Inventario** | Productos | `/productos` |
| | Pedidos a proveedores | `/pedidos-proveedores` |
| | Proveedores | `/proveedores` |
| | Historial de Inventario | `/historial-inventario` |
| | Kardex | `/kardex` |
| | Traspasos | `/traspasos` |
| | Mermas y Toma física | `/inventario-avanzado` |
| | Categorías | `/categorias` |
| **Finanzas** | Caja | `/caja` |
| | Ingresos | `/ingresos` |
| | Gastos | `/compras` |
| | Comisiones | `/comisiones` |
| | Contabilidad | `/contabilidad` |
| | Ganancias | `/ganancias` |
| | Conciliación bancaria | `/conciliacion` |
| **Reportes** | Ventas, Comparativa de ventas, Gastos, Órdenes, Caja | `/reporte-*`, `/comparativa-ventas` |
| **Contactos** | Clientes | `/clientes` |
| | Fidelizar clientes | `/recuperar-clientes` |
| **Catálogo de equipos** | Marcas, Modelos, Tipos de equipo | `/marcas`, `/mods`, `/tipos-equipos` |
| **Asistente técnico** | Compatibilidades, Hardware free, Software free | `/asistente-tecnico/*` |
| **Sistema** | Configuración, Impuestos, Firmas de órdenes, WhatsApp de órdenes, Usuarios, Roles y Permisos, Sucursales | varias |
| Cuenta | Renovaciones, Novedades, Soporte (chat) | `/renovaciones`, `/novedades`, `/soporte` |

---

## 3. Dashboard (`/inicio`)

- **Tarjetas del día**, cada una con su variación en % y un enlace "Ver detalles": Ventas, Reparaciones, Abonos, Ingresos, Gastos y **Total caja del día** (lo del día + la apertura).
- **Utilidad del día** = ventas sin IVA + reparaciones − costo de lo vendido − gastos. Se puede fijar una **meta diaria**.
- **Dinero que te deben:** créditos de ventas y créditos de reparaciones, con número de clientes y monto.
- **IVA del mes:** lo cobrado hoy, este mes y el mes anterior, como referencia para la declaración.
- **Cajas abiertas ahora:** usuario, sucursal, desde cuándo, apertura, totales por forma de pago (efectivo, tarjeta, transferencia, crédito, otros) y efectivo esperado.
- **Balance diario** y efectivo en caja.
- **Indicadores del taller:** recibidas hoy, en curso, esperando repuesto, entregadas hoy, **abiertas hace más de 7 días** y **tiempo promedio de cierre (30 días)**.
- **Órdenes activas por técnico.**
- **Distribución de órdenes por estado**, con cantidad y %.
- **Gráfico de ventas mensuales** del año.
- **Últimas 5 órdenes**, con su saldo pendiente.
- **Top 10 de productos más vendidos.**

## 4. Órdenes de trabajo (núcleo del sistema)

### 4.1 Crear orden: asistente de 6 pasos con resumen lateral fijo
1. **Cliente y técnico:** código correlativo automático (6 dígitos, ej. 000162), fecha de ingreso, técnico responsable y cliente (selector con búsqueda, "Público general" por defecto y botón *Agregar cliente* en modal).
2. **Datos del equipo:** tipo, marca y modelo (selects con búsqueda y botón **+** para crear al vuelo), y serie/IMEI. **Seguridad:** patrón (dibujo), contraseña o sin bloqueo.
3. **Verificación del equipo:** interruptores Sí/No editables, por ejemplo *Enciende, Golpes, Humedad, Cargador* y 4 campos "Otro" cuyo texto se cambia con un clic.
4. **Detalles del problema:** falla (obligatoria), diagnóstico o acción a tomar, casilla "ingresa por garantía" (aparece etiquetada en la lista), plazo de garantía (sin garantía / 1 sem / 2 sem / 1 mes / 2 m / 3 m / 6 m / 1 año) y observación de reparación.
5. **Evidencias:** fotos JPG/PNG de hasta 4 MB, desde el equipo o **subidas desde el celular escaneando un QR**. El límite de fotos depende del plan.
6. **Repuestos y presupuesto:** repuestos tomados del inventario (**se descuentan del stock de la sucursal**), presupuesto de mano de obra con **calculadora por tiempo** (horas × tarifa por hora, que se puede guardar como tarifa del taller), total de repuestos, **anticipo** con su método de pago (efectivo, tarjeta débito, tarjeta crédito, transferencia) y total.
   - **Firma del cliente en la recepción** (canvas, se puede abrir a pantalla completa) con un texto de conformidad.

El panel lateral resume cliente, equipo, serie, trabajo, total, anticipo y saldo, con los botones Guardar / Cancelar.

### 4.2 Orden Express
Es un formulario corto en una sola pantalla: tipo (obligatorio), serie, marca, modelo, falla (obligatoria), observaciones, técnico (obligatorio), presupuesto y contraseña o PIN. El cliente puede ser "Público general" o uno nuevo creado en línea.

### 4.3 Estados de una orden
`Por reparar` → `En diagnóstico` → `Esperando repuesto` → `Reparado` → `Entregado`
Estados alternativos: `Sin reparación`, `Entregado S/R` (sin reparar), `Equipo abandonado`, `En garantía`, `En espera`.

- Cada cambio de estado queda en **Movimientos**, con estado, usuario y fecha y hora.
- Se puede exigir una **firma al entregar**.
- Se puede exigir un **informe técnico finalizado antes de pasar a Entregado**.

### 4.4 Listado (`/ordenes`)
- **Contadores por estado** arriba, que también funcionan como filtros.
- Filtros: sucursal (esta o todas) y "solo por garantía".
- Columnas: N°, cliente (con teléfono), equipo, falla, **cambiar estado** (directamente en la tabla), acciones, técnico, fecha de ingreso, costo, abono y pendiente.
- **Acciones por fila:** ver detalle (modal), notificar por WhatsApp, copiar el enlace público de estado, informes técnicos, imprimir, editar y eliminar. **No se puede eliminar una orden entregada.**

### 4.5 Detalle (modal) y edición
- Detalle: cliente, teléfono, técnico, garantía, equipo, verificación (chips de colores), falla, costo, abonado, **pendiente por pagar** y movimientos.
- Edición (`/ordenes/{id}/editar`): lo mismo que crear, más el cambio de estado, **abono nuevo** con su método de pago, total abonado, saldo pendiente y botones Imprimir / WhatsApp / Copiar enlace.

### 4.6 Seguimiento del cliente
- **Página pública de estado** a la que se llega por enlace o QR. Se puede configurar si muestra el total y el saldo.
- `/status`: búsqueda interna por número de orden o código QR.

### 4.7 WhatsApp
- **No usa la API oficial:** abre `wa.me` con el mensaje armado, y el usuario lo envía desde su WhatsApp.
- Hay **una plantilla por estado**, editable, con variables: `{cliente} {codigo} {equipo} {estado} {empresa} {sucursal} {total} {garantia_hasta} {enlace}`.
- Ejemplo de la plantilla "Reparado": *"Hola {cliente}, buenas noticias: tu {equipo} (orden N° {codigo}) ya está {estado} y listo para retirar en {empresa} ({sucursal}). Saldo pendiente: {total}. Detalle de tu orden: {enlace}"*

### 4.8 Informes técnicos
- **Plantillas** de inspección, diagnóstico y entrega armadas con bloques: texto, listas de revisión, fotos y firma.
- Cada orden puede tener varios informes, en borrador o finalizados.

### 4.9 Abonos e historial
- `/abonos-ordenes`: N° de orden, cliente, fecha, valor, método, quién cobró, estado y acciones, con filtro por fechas.
- `/historial-ordenes`: auditoría de código, cliente, estado, fecha, hora y usuario.

## 5. Ventas

### 5.1 POS (`/crear-venta`)
- Agregar productos por **código de barras o escáner**, desde el **catálogo visual** o como **concepto libre** (sin producto).
- Columnas: código, producto, cantidad, precio unitario, descuento, impuesto, subtotal y total.
- Acciones por línea: cantidad, impuesto, descuento individual, cambiar precio y eliminar.
- **Ventas en espera:** se pueden aparcar varias ventas y retomarlas.
- **"Código ruleta":** canjea premios de la ruleta de fidelización.
- Cliente, ticket, garantía (sin garantía / 15 / 30 / 60 / 90 días / 6 meses / 1 año / otro), comentario e impuesto global.
- "Procesar cobro" con varias formas de pago, incluida la **venta a crédito**.

### 5.2 Venta Express
Cobro rápido de un concepto, con forma de pago, efectivo recibido (calcula el vuelto) y código de transacción.

### 5.3 Cotizaciones
- Estados: `Pendiente`, `Aceptada`, `Convertida` (en venta u orden) y `Vencida`, con fecha de vencimiento.
- Opciones de *Cotización rápida* y *Nueva cotización*.

### 5.4 Créditos de clientes
- Lista por cliente: número de créditos, valor total, saldo y estado.
- Planes de **cuotas semanales**, abono por cuota o al saldo, y panel de cuotas por cobrar. Esta es su función nueva más destacada.

### 5.5 Garantías
Búsqueda de una venta u orden para revisar si la garantía sigue vigente.

### 5.6 Mi Negocio Web
Catálogo público de la tienda: publicar o despublicar, enlace, nombre, frase, mensaje en la franja, logo, banner, sucursal de la que toma el stock, mostrar solo productos con stock, precio de oferta, WhatsApp para recibir pedidos, Google Maps y redes sociales.

## 6. Inventario

- **Productos:** código o serie, imagen, nombre, stock, precio de compra, precio de venta, categoría y fecha. Tiene **ofertas**, **carga desde Excel**, alertas de **agotados** y **stock bajo**, vista de catálogo o de lista, y exportación a CSV/PDF.
- **Pedidos a proveedores (sugeridos):** se calculan **por stock mínimo** o **por ventas** ("cubrir N días de venta" según las ventas de los últimos 7 a 90 días), con filtro por proveedor y exportación a Excel/PDF.
- **Proveedores:** nombre, tipo y número de documento, teléfono, email y dirección.
- **Kardex** por producto o general, con entradas, salidas y saldo.
- **Traspasos entre sucursales:** producto, de qué sucursal a cuál, cantidad, **seriales** y responsable.
- **Mermas:** motivo (dañado, vencido, pérdida o robo, uso interno, devuelto a proveedor, otro), nota y costo.
- **Toma física:** se ingresa el conteo por **percha o ubicación**, el sistema compara contra su stock, muestra la diferencia y permite aplicar los ajustes.
- **Categorías.**

## 7. Finanzas

- **Caja por usuario:** apertura con monto inicial, movimientos y cierre comparando **esperado y contado** por forma de pago, con la **diferencia** resultante. Opción de **exigir caja abierta para cobrar**, de la que pueden quedar exentos los administradores. Historial de cajas.
- **Ingresos y gastos:** código, concepto, descripción, forma de pago, monto, fecha y hora, responsable y sucursal. Los conceptos vienen predefinidos:
  - Ingresos: apertura de caja, reparación rápida, comisiones por recargas, intereses, préstamo recibido, reposición de efectivo, venta de equipos o chatarra, etc.
  - Gastos: alimentación, alquiler, repuestos, depósitos o retiros de caja, devoluciones, courier, herramientas, software, marketing, pago a proveedores, servicios básicos, sueldos o adelantos, etc.
- **Comisiones:** por empleado sobre órdenes o ventas, con base, porcentaje y monto. Estados `Pendiente`, `Pagada` y `Cancelada`, con opción de "marcar como pagadas" en lote.
- **Contabilidad:** libro diario automático con asientos (cuenta, debe, haber) y vista avanzada exportable.
- **Ganancias:** ingresos, costo de repuestos y productos, gastos y comisiones, ganancia neta y margen. Gráfico mensual, desglose por origen (reparaciones, ventas, otros), **ganancia por técnico** y "lo que más ganancia deja".
- **Conciliación bancaria:** se sube el extracto del banco en Excel o CSV, se mapean las columnas (fecha, crédito, monto) y se cruza con los movimientos del sistema.

## 8. Reportes
Todos tienen rangos rápidos (hoy, ayer, 7 días, 30 días, este mes, mes pasado) y exportación a Excel, CSV o PDF.
- **Ventas:** nota, fecha, vendedor, cliente, origen, forma de pago, sucursal y total. Opción de incluir los repuestos de órdenes entregadas.
- **Órdenes:** fecha, orden, técnico, cliente, marca y modelo, falla, estado, motivo y total, con filtro por estado y técnico.
- **Caja:** tipo (venta, abono a crédito, reparación, ingreso, gasto), nota, responsable, forma de pago y monto.
- **Gastos** y **comparativa de ventas** entre periodos.

## 9. Clientes y fidelización
- **Clientes:** DNI o ID, nombre, teléfono y fecha de alta, con importación desde Excel y exportación.
- **Fidelizar:** listas de agenda por sucursal, *más compraron*, *menos compraron* y *no volvieron* (en 30 días, 90 días o el año). **Ruleta de premios** que se envía por WhatsApp, con premios pendientes o por entregar que se canjean con un código en el POS.

## 10. Configuración y seguridad
- **Empresa:** tipo de ID tributario (RUC, NIT, RFC, etc.), razón social, representante legal, dirección, teléfono, horario y logo.
- **País, moneda y zona horaria**, con segunda moneda y tasa de cambio.
- **Términos y condiciones** separados para el ticket de órdenes y el de ventas. Opción de imprimir la contraseña o patrón del equipo en el ticket.
- **Impuestos:** nombre y porcentaje, con uno predeterminado (en Perú: IGV).
- **Firmas:** obligatoria al recibir y/o al entregar, con texto legal.
- **Usuarios:** nombre, correo, perfil, sucursales asignadas, estado y último acceso.
- **Roles y permisos:** los roles son *Administrador* (tiene todo), *Técnico* y *Vendedor*. Es una matriz **módulo × acción** (`ver, crear, editar, eliminar, imprimir, ver costos, ver cajas de todos`), y se pueden ajustar permisos de un usuario en particular. Solo el administrador gestiona usuarios, configuración y permisos.
- **Sucursales:** nombre, teléfono, dirección, horario, moneda y zona, con interruptor de activa/inactiva. La cantidad depende del plan (1 de 2).

---

## 11. Reglas de negocio clave para replicar

1. El **correlativo de las órdenes** va por empresa (o por sucursal) y es de 6 dígitos.
2. **Saldo de la orden** = presupuesto + repuestos − Σ abonos (el anticipo es el primer abono).
3. Los repuestos de una orden **descuentan el stock de la sucursal** y se registran en el kardex.
4. Todo cobro (venta, abono, anticipo) **va a la caja abierta del usuario**, que puede ser obligatoria.
5. Cada cambio de estado genera un **movimiento** (auditoría) y ofrece el aviso por WhatsApp.
6. Una orden **entregada no se puede eliminar**.
7. **Garantía** en días o meses calculada desde la entrega, expuesta como `{garantia_hasta}`.
8. **Comisiones** por técnico o vendedor calculadas sobre la base de la orden o la venta.
9. **Utilidad** = ingresos − costo de repuestos o productos − gastos − comisiones.
10. Todo se filtra por **empresa → sucursal**, con permisos de "ver todas mis sucursales".

## 12. Modelo de datos propuesto (borrador)

```
empresas(id, nombre, ruc, plan, vence_el, moneda, zona_horaria, ...)
sucursales(id, empresa_id, nombre, direccion, telefono, horario, activa)
usuarios(id, empresa_id, nombre, email, password, rol_id, activo, ultimo_acceso)
usuario_sucursal(usuario_id, sucursal_id)
roles(id, empresa_id, nombre) / permisos(rol_id|usuario_id, modulo, accion)

clientes(id, empresa_id, sucursal_id, doc_tipo, doc_numero, nombre, telefono, email, direccion)
tipos_equipo / marcas / modelos(marca_id, tipo_id)

ordenes(id, empresa_id, sucursal_id, codigo, cliente_id, tecnico_id, tipo_id, marca_id, modelo_id,
        serie_imei, seguridad_tipo, seguridad_valor(cifrado), falla, diagnostico, observacion,
        es_garantia, garantia_dias, presupuesto, total_repuestos, total, estado, fecha_ingreso,
        fecha_entrega, firma_recepcion, firma_entrega, token_publico)
orden_verificaciones(orden_id, item, valor_bool)
orden_fotos(orden_id, ruta)
orden_repuestos(orden_id, producto_id, cantidad, precio, costo)
orden_movimientos(orden_id, estado, usuario_id, nota, created_at)
pagos(id, origen_tipo[orden|venta|credito], origen_id, caja_id, metodo, monto, referencia, usuario_id)
informes(id, orden_id, plantilla_id, contenido_json, estado) / informe_plantillas

productos(id, empresa_id, categoria_id, codigo, nombre, imagen, precio_compra, precio_venta,
          precio_oferta, stock_minimo, maneja_series)
stock(producto_id, sucursal_id, cantidad, ubicacion)
kardex(id, producto_id, sucursal_id, tipo, cantidad, costo, saldo, origen_tipo, origen_id, usuario_id)
traspasos / mermas / tomas_fisicas / proveedores / pedidos_proveedor

ventas(id, sucursal_id, numero, cliente_id, usuario_id, subtotal, descuento, impuesto, total,
       estado[en_espera|pagada|credito|anulada], garantia_dias)
venta_items(venta_id, producto_id|concepto, cantidad, precio, descuento, impuesto, costo)
cotizaciones / cotizacion_items (estado, vence_el, convertida_a)
creditos(id, cliente_id, origen, total, saldo, frecuencia) / credito_cuotas(credito_id, n, vence, monto, pagado)

cajas(id, sucursal_id, usuario_id, abierta_en, cerrada_en, monto_inicial, esperado_json, contado_json, diferencia)
movimientos_financieros(id, sucursal_id, tipo[ingreso|gasto], concepto_id, monto, metodo, usuario_id, caja_id)
comisiones(id, usuario_id, origen_tipo, origen_id, base, porcentaje, monto, estado)
plantillas_whatsapp(empresa_id, estado, texto)
configuraciones(empresa_id, clave, valor)
```

## 13. Oportunidades para que nuestro sistema sea mejor

- **WhatsApp con API** (Cloud API o un proveedor) para enviar avisos automáticos, en lugar de abrir `wa.me` a mano.
- **Facturación electrónica SUNAT** (boletas y facturas). OrderFix solo tiene "tickets" e IVA de referencia.
- **Catálogos limpios:** en OrderFix hay marcas duplicadas (ACER/ACER, CANON×3, GIGABYTE/GYGABYTE) y clientes duplicados. Conviene validar unicidad y ofrecer una función para fusionar duplicados.
- **Verificación del equipo configurable por tipo de equipo:** distinta para laptop, celular o impresora, en vez de 4 campos "Otro" genéricos.
- **Cifrar las contraseñas y patrones** de los equipos de los clientes.
- **Seguridad:** agregar HSTS, X-Frame-Options y CSP, ocultar `X-Powered-By` y usar 2FA para administradores.
- **SLA y alertas:** avisar cuando una orden pasa N días sin moverse. OrderFix solo cuenta las de más de 7 días.
- **Portal del cliente** con historial de todas sus órdenes, no solo una página por orden.
