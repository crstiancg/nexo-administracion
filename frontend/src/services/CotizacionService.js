import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition. Los cambios de estado sí: no llevan datos.
 */
class CotizacionService {
  static async getData (config) {
    return (await api.get('api/cotizaciones', config)).data
  }

  static async get (id) {
    return (await api.get(`api/cotizaciones/${id}`)).data
  }

  // Devuelve la cotización ya convertida, con `pedido` { id, codigo }.
  // `items` ([{ variante_id, cantidad }]): sólo lo que pide el cliente.
  static async convertir (id, items) {
    return (await api.post(`api/cotizaciones/${id}/convertir`, items ? { items } : {})).data
  }

  static async rechazar (id) {
    return (await api.post(`api/cotizaciones/${id}/rechazar`)).data
  }
}

export default CotizacionService
