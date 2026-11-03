/**
 * Forma inicial del documento de inventario. La clave `movimiento` es la
 * misma que valida MovimientoInventarioRequest (`movimiento.lineas.0.cantidad`…).
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo cargado al siguiente form.
 *
 * @param {'entrada'|'salida'|'ajuste'|'traslado'} tipo
 */
export default function formMovimiento (tipo) {
  return {
    movimiento: {
      referencia: '',
      observacion: '',
      ...(tipo === 'salida' ? { motivo: null } : {}),
      ...(tipo === 'traslado' ? { sede_destino_id: null } : {}),
      lineas: []
    }
  }
}

/**
 * Una línea para la variante elegida. `variante` viaja sólo para mostrar
 * (producto, presentación, color, stock): el backend valida y usa `variante_id`.
 *
 * @param {'entrada'|'salida'|'ajuste'|'traslado'} tipo
 * @param {{ id: number, stock: number, costo_promedio: string|null }} variante  stock = el de tu sede
 */
export function nuevaLinea (tipo, variante) {
  const linea = { variante_id: variante.id, variante }

  // Productos con lotes: la entrada (y lo que sobre en un conteo) dice a
  // qué lote va; una salida puede elegir de cuál sale (vacío = el que vence
  // primero).
  if (variante.maneja_lotes) {
    if (tipo === 'entrada' || tipo === 'ajuste') Object.assign(linea, { lote: '', vence_at: '' })
    if (tipo === 'salida') linea.lote_id = null
  }

  if (tipo === 'entrada') {
    linea.cantidad = ''
    // Se propone el costo promedio actual; casi siempre la compra se repite.
    linea.costo_unitario = variante.costo_promedio !== null
      ? Number(variante.costo_promedio).toFixed(2)
      : ''
  } else if (tipo === 'salida' || tipo === 'traslado') {
    linea.cantidad = ''
  } else {
    // Ajuste: arranca con el stock del sistema; se corrige lo que difiera.
    linea.stock_real = String(variante.stock)
  }

  return linea
}
