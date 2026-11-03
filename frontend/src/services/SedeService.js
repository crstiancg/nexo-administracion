import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition, que valida y envía contra la misma URL.
 */
class SedeService {
  static async getData (config) {
    return (await api.get('api/sedes', config)).data
  }

  static async get (id) {
    return (await api.get(`api/sedes/${id}`)).data
  }

  static async delete (id) {
    return api.delete(`api/sedes/${id}`)
  }

  // Las activas, por nombre: para selectores.
  static async activas () {
    return (await api.get('api/sedes', { params: { rowsPerPage: 0, activo: 1, order_by: 'nombre' } })).data.data
  }
}

export default SedeService
