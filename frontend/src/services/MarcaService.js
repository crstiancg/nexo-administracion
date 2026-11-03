import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition, que valida y envía contra la misma URL.
 */
class MarcaService {
  static async getData (config) {
    return (await api.get('api/marcas', config)).data
  }

  static async get (id) {
    return (await api.get(`api/marcas/${id}`)).data
  }

  static async delete (id) {
    return api.delete(`api/marcas/${id}`)
  }
}

export default MarcaService
