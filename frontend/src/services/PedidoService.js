import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition. Los cambios de estado sí: no llevan datos.
 */
class PedidoService {
  static async getData (config) {
    return (await api.get('api/pedidos', config)).data
  }

  static async get (id) {
    return (await api.get(`api/pedidos/${id}`)).data
  }

  static async confirmar (id) {
    return (await api.post(`api/pedidos/${id}/confirmar`)).data
  }

  static async entregar (id) {
    return (await api.post(`api/pedidos/${id}/entregar`)).data
  }

  static async cancelar (id) {
    return (await api.post(`api/pedidos/${id}/cancelar`)).data
  }

  // Deshace una venta hecha: vuelven el stock y la plata (desde la caja de hoy).
  static async anular (id, motivo) {
    return (await api.post(`api/pedidos/${id}/anular`, { motivo })).data
  }
}

export default PedidoService
