/**
 * Forma inicial del form de productos. La clave `producto` es la misma que
 * valida StoreProductoRequest (`producto.nombre`, `producto.variantes.0.sku`…).
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo tipeado al siguiente form.
 *
 * Al editar va como POST con `_method: 'PUT'`: con fotos nuevas viaja en
 * multipart, y PHP no parsea multipart en un PUT (llegaría vacío).
 *
 * @param {boolean} editando
 */
export default function formProducto (editando = false) {
  return {
    ...(editando ? { _method: 'PUT' } : {}),
    producto: {
      nombre: '',
      categoria_id: null,
      marca_id: null,
      descripcion: '',
      precio: '',
      activo: true,
      // Con lotes, cada entrada pide lote y vencimiento. Prendido por defecto:
      // en materiales de construcción casi todo vence (cemento, aditivos…).
      maneja_lotes: true,
      // Stock inicial de las presentaciones nuevas: entra como una entrada de
      // inventario ("Alta de producto") con este costo (ajustable por fila).
      costo_compra: '',
      referencia_compra: '',
      // Fotos: { id, url, nombre } guardadas o { archivo: File, url, nombre } nuevas.
      archivos: [],
      variantes: []
    }
  }
}

let ultimoUid = 0

/**
 * Una fila de presentación (en la base, una variante). `uid` es la key del
 * v-for (las nuevas no tienen id) y `skuManual` marca que el SKU lo escribió
 * el usuario: desde ahí deja de regenerarse al cambiar nombre, presentación o
 * color. Ninguno de los dos lo valida el backend, así que no se guardan.
 * `stock` es sólo para mostrar.
 */
export function nuevaVariante (datos = {}) {
  return {
    uid: ++ultimoUid,
    id: null,
    presentacion: '',
    unidad_id: null,
    color_id: null,
    sku: '',
    // El de fábrica (EAN-13): lo registra el usuario, el sistema no lo genera.
    codigo_barras: '',
    precio: '',
    // Para clientes mayoristas (empresas). Vacío = no tiene.
    precio_mayor: '',
    stock: 0,
    // [{ sede_id, sede, cantidad }]: el desglose, sólo para mostrar.
    stocks: [],
    // [{ sede_id, activo, precio, stock_minimo }]: cómo la vende cada sede
    // (es lo que viaja; la cantidad de cada sede está en `stocks`).
    sedes: [],
    // Sólo variantes nuevas: unidades que entran al crearla y su costo si
    // difiere del general de la compra.
    stock_inicial: '',
    costo_unitario: '',
    // Con lotes: a qué lote entra ese stock inicial.
    lote: '',
    vence_at: '',
    con_movimientos: false,
    archivos: [],
    skuManual: false,
    ...datos
  }
}
