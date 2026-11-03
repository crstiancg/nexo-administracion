import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition, que valida y envía contra la misma URL.
 */
class RolService {
  static async getData (config) {
    return (await api.get('api/roles', config)).data
  }

  /** @returns {Promise<{ rol: { id: number, name: string }, permisosSelected: number[] }>} */
  static async get (id) {
    return (await api.get(`api/roles/${id}`)).data
  }

  static async delete (id) {
    return api.delete(`api/roles/${id}`)
  }
}

export default RolService
