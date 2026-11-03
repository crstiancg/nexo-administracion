import { precioPara } from '@/utils/precios'

/**
 * Forma inicial del form de pedidos. La clave `pedido` es la misma que valida
 * StorePedidoRequest (`pedido.items.0.cantidad`…). Los totales NO van: los
 * calcula el backend; acá se muestran sólo como vista previa.
 *
 * Función y no objeto: useForm guarda estos datos como "originales" para el
 * reset(), y un objeto compartido arrastraría lo cargado al siguiente form.
 */
export default function formPedido () {
  return {
    pedido: {
      // null = "Cliente varios".
      cliente_id: null,
      canal: 'mostrador',
      descuento: '',
      observacion: '',
      items: []
    }
  }
}

/**
 * Un ítem para la variante elegida, con su precio como propuesta: el de hoy
 * o, para un cliente mayorista, el por mayor. `variante` viaja sólo para
 * mostrar (el backend usa variante_id). `precio_auto` es el que propuso el
 * sistema: si el usuario lo cambia a mano, cambiar de cliente no lo pisa.
 *
 * @param {{ id: number, precio: string|null, precio_mayor?: string|null }} variante
 * @param {{ mayorista?: boolean }|null} cliente
 */
export function nuevoItem (variante, cliente = null) {
  const sinPrecio = variante.precio === null || variante.precio === undefined
  const { precio, porMayor } = precioPara(variante.precio, variante.precio_mayor, cliente)

  return {
    variante_id: variante.id,
    variante,
    cantidad: '1',
    precio_unitario: sinPrecio ? '' : precio,
    precio_auto: sinPrecio ? null : precio,
    por_mayor: !sinPrecio && porMayor
  }
}

/**
 * Al cambiar de cliente, los precios que puso el sistema pasan al que le
 * toca al nuevo (por mayor o normal). Los escritos a mano no se tocan.
 */
export function reprecio (items, cliente) {
  for (const item of items) {
    if (item.precio_auto === null || item.precio_auto === undefined || item.precio_unitario !== item.precio_auto) continue
    const { precio, porMayor } = precioPara(item.variante.precio, item.variante.precio_mayor, cliente)
    item.precio_unitario = precio
    item.precio_auto = precio
    item.por_mayor = porMayor
  }
}
