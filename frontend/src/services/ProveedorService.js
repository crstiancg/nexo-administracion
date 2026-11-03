import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition, que valida y envía contra la misma URL.
 */
class ProveedorService {
  static async getData (config) {
    return (await api.get('api/proveedores', config)).data
  }

  static async get (id) {
    return (await api.get(`api/proveedores/${id}`)).data
  }

  static async delete (id) {
    return api.delete(`api/proveedores/${id}`)
  }

  /**
   * Busca el RUC primero en la base y después en SUNAT.
   * `origen`: 'local' (viene `proveedor`), 'api' (viene `datos`) o null.
   */
  static async consultarRuc (ruc) {
    return (await api.get('api/proveedores/consultar-ruc', { params: { ruc } })).data
  }
}

export default ProveedorService
