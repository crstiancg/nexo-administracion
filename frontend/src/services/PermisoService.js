import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hacen los forms con useForm() de
 * Precognition, que validan y envían contra la misma URL.
 */
class PermisoService {
  static async getData (config) {
    return (await api.get('api/permisos', config)).data
  }

  static async get (id) {
    return (await api.get(`api/permisos/${id}`)).data
  }

  /**
   * Rutas protegidas que todavía no tienen su permiso, agrupadas por recurso.
   * Es lo único que se puede elegir al crear permisos.
   * @returns {Promise<Array<{ recurso: string, nombre: string, rutas: Array<{ name: string, description: string, metodo: string, uri: string }> }>>}
   */
  static async rutasDisponibles () {
    return (await api.get('api/permisos/rutas-disponibles')).data
  }

  static async delete (id) {
    return api.delete(`api/permisos/${id}`)
  }
}

export default PermisoService
