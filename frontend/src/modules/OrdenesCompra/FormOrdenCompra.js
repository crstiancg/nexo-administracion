/**
 * Forma inicial del form de órdenes de compra. La clave `orden` es la misma
 * que valida StoreOrdenCompraRequest. El total NO va: lo calcula el backend.
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo cargado al siguiente form.
 */
export default function formOrdenCompra () {
  return {
    orden: {
      proveedor_id: null,
      fecha_entrega: '',
      condiciones: '',
      observacion: '',
      items: []
    }
  }
}

/**
 * Una línea a partir de una presentación (del buscador o de Por reponer).
 * Cantidad vacía: la pone quien arma la orden. Costo: el promedio de hoy
 * como referencia (se cambia por el precio del proveedor, o se deja vacío).
 */
export function nuevoItem (variante) {
  return {
    variante_id: variante.id,
    variante,
    cantidad: '',
    costo_unitario: variante.costo_promedio !== null && variante.costo_promedio !== undefined
      ? Number(variante.costo_promedio).toFixed(2)
      : ''
  }
}
