import { api } from '@/boot/axios'

/**
 * Lo que junta varios módulos (PanelController). Cada bloque llega sólo si el
 * usuario puede ver su pantalla: un bloque en null no se muestra.
 */
class PanelService {
  // Resumen de la sede del usuario: ventas, caja, pendientes, inventario.
  static async dashboard () {
    return (await api.get('api/panel/dashboard')).data
  }

  // La campana: [{ clave, nivel, titulo, detalle, cantidad, to }].
  static async alertas () {
    return (await api.get('api/panel/alertas')).data.data
  }

  // Búsqueda global: { productos, pedidos, clientes, cotizaciones }.
  static async buscar (q, config = {}) {
    return (await api.get('api/panel/buscar', { params: { q }, ...config })).data.data
  }
}

export default PanelService
