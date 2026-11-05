// Validez por defecto (igual que StoreCotizacionRequest::DIAS_VALIDEZ).
export const DIAS_VALIDEZ = 7

// Opciones rápidas de la proforma (se puede escribir otra).
export const FORMAS_PAGO = ['Contado', '50% de adelanto y saldo contra entrega', 'Crédito a 15 días', 'Crédito a 30 días']
export const ENTREGAS = [
  'Con posibilidad de entrega en oficina o domicilio del cliente.',
  'Recojo en tienda.',
  'Entrega inmediata, sujeta a stock.'
]
// Casilleros de una "PC armada", en el orden de la proforma de Nexo.
export const COMPONENTES_PC = [
  'Procesador',
  'Placa madre',
  'Tarjeta de video',
  'Memoria RAM',
  'Disco sólido SSD',
  'Disco mecánico HDD',
  'Case',
  'Fuente de poder',
  'Monitor',
  'Kit completo T/M/PM/A',
  'Teclado',
  'Mouse',
  'Parlante',
  'Auricular',
  'Estabilizador'
]

export const GARANTIAS = [
  '1 año',
  '6 meses',
  '3 meses',
  '1 mes',
  '7 días',
  'Garantía del fabricante',
  'Sin garantía'
]

/**
 * Forma inicial del form de cotizaciones. La clave `cotizacion` es la misma
 * que valida StoreCotizacionRequest. Los totales NO van: los calcula el
 * backend; acá se muestran sólo como vista previa.
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo cargado al siguiente form.
 *
 * Al editar va como POST con `_method: 'PUT'`: con fotos nuevas viaja en
 * multipart, y PHP no parsea multipart en un PUT (llegaría vacío).
 *
 * @param {boolean} editando
 */
export default function formCotizacion (editando = false) {
  return {
    ...(editando ? { _method: 'PUT' } : {}),
    cotizacion: {
      // normal: ítems sueltos. pc: los componentes de una computadora armada.
      tipo: 'normal',
      // En la PC armada se puede imprimir sólo el total (como el Excel de Nexo).
      mostrar_precios: true,
      cliente_id: null,
      // Vacía: si no se pone, el backend la deja en DIAS_VALIDEZ días.
      valida_hasta: '',
      // Salen impresos en la proforma; se proponen los de siempre.
      forma_pago: FORMAS_PAGO[0],
      entrega: ENTREGAS[0],
      // Vacía: no sale en la proforma.
      garantia: '',
      condiciones: '',
      descuento: '',
      observacion: '',
      items: []
    }
  }
}

let ultimoUid = 0

/** Key del v-for: los ítems libres no tienen variante_id que la haga única. */
export function conUid (item) {
  return { uid: ++ultimoUid, archivos: [], componente: null, ...item }
}

/** Un casillero de la PC que quedó sin llenar: no se guarda ni se imprime. */
export function casilleroVacio (item) {
  return Boolean(item.componente) && !item.variante && !item.descripcion?.trim()
}

/**
 * Lo que la tienda no tiene: se describe a mano y se cotiza igual (por
 * encargo). No tiene stock ni pasa al pedido al convertir.
 */
export function nuevoItemLibre (componente = null) {
  return conUid({
    variante_id: null,
    variante: null,
    componente,
    descripcion: '',
    unidad: 'und',
    cantidad: '1',
    precio_unitario: ''
  })
}
