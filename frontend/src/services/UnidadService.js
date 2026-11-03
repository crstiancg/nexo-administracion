import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition, que valida y envía contra la misma URL.
 */
class UnidadService {
  static async getData (config) {
    return (await api.get('api/unidades', config)).data
  }

  static async get (id) {
    return (await api.get(`api/unidades/${id}`)).data
  }

  static async delete (id) {
    return api.delete(`api/unidades/${id}`)
  }
}

export default UnidadService
