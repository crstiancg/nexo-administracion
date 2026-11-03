import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition. Los cambios de estado sí.
 */
class OrdenCompraService {
  static async getData (config) {
    return (await api.get('api/ordenes-compra', config)).data
  }

  static async get (id) {
    return (await api.get(`api/ordenes-compra/${id}`)).data
  }

  static async enviar (id) {
    return (await api.post(`api/ordenes-compra/${id}/enviar`)).data
  }

  static async anular (id, motivo) {
    return (await api.post(`api/ordenes-compra/${id}/anular`, { motivo })).data
  }
}

export default OrdenCompraService
