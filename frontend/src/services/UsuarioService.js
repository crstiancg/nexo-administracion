import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition, que valida y envía contra la misma URL.
 */
class UsuarioService {
  static async getData (config) {
    return (await api.get('api/usuarios', config)).data
  }

  /** @returns {Promise<{ user: object, rolesSelected: number[], permisosSelected: number[] }>} */
  static async get (id) {
    return (await api.get(`api/usuarios/${id}`)).data
  }

  static async delete (id) {
    return api.delete(`api/usuarios/${id}`)
  }

  /** @returns {Promise<{ id: number, active: boolean }>} */
  static async toggleActive (id) {
    return (await api.patch(`api/usuarios/${id}/toggle-active`)).data
  }

  static async getSesiones (userId) {
    return (await api.get(`api/usuarios/${userId}/sesiones`)).data
  }

  static async revocarSesion (userId, tokenId) {
    return api.delete(`api/usuarios/${userId}/sesiones/${tokenId}`)
  }
}

export default UsuarioService
