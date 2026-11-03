import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition, que valida y envía contra la misma URL.
 */
class ClienteService {
  static async getData (config) {
    return (await api.get('api/clientes', config)).data
  }

  static async get (id) {
    return (await api.get(`api/clientes/${id}`)).data
  }

  static async delete (id) {
    return api.delete(`api/clientes/${id}`)
  }

  /**
   * Busca el documento primero en la base y después en RENIEC/SUNAT.
   * `origen`: 'local' (viene `cliente`), 'api' (viene `datos`) o null.
   */
  static async consultarDocumento (tipo, numero) {
    return (await api.get('api/clientes/consultar-documento', { params: { tipo, numero } })).data
  }
}

export default ClienteService
