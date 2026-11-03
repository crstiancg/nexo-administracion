import { api } from '@/boot/axios'

/**
 * Registrar no pasa por acá: lo hace el form con useForm() de Precognition.
 */
class CompraService {
  static async getData (config) {
    return (await api.get('api/compras', config)).data
  }

  static async get (id) {
    return (await api.get(`api/compras/${id}`)).data
  }

  static async anular (id, motivo) {
    return (await api.post(`api/compras/${id}/anular`, { motivo })).data
  }
}

export default CompraService
