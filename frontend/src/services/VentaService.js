import { api } from '@/boot/axios'

class VentaService {
  /**
   * Catálogo del punto de venta (paginado): productos activos con portada,
   * stock, vendidos y variantes. Filtros: search, categoria_id, marca_id,
   * color_id, con_stock, order_by (vendidos | nombre | precio | -precio | stock).
   */
  static async catalogo (config) {
    return (await api.get('api/ventas/catalogo', config)).data
  }

  /**
   * Ficha de sólo lectura de un producto para el vendedor: precio de hoy,
   * stock aquí y en otras sedes, lotes con su vencimiento.
   */
  static async ficha (id) {
    return (await api.get(`api/ventas/catalogo/${id}`)).data
  }

  /**
   * Venta de mostrador: crea el pedido, lo confirma, lo cobra y lo entrega
   * en una sola transacción. Devuelve el pedido completo (para el ticket).
   */
  static async registrar (venta) {
    return (await api.post('api/ventas', { venta })).data
  }
}

export default VentaService
