import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition, que valida y envía contra la misma URL.
 */
class CategoriaService {
  static async getData (config) {
    return (await api.get('api/categorias', config)).data
  }

  static async get (id) {
    return (await api.get(`api/categorias/${id}`)).data
  }

  static async delete (id) {
    return api.delete(`api/categorias/${id}`)
  }
}

export default CategoriaService
