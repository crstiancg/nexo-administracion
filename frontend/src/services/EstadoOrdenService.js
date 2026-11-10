import { api } from '@/boot/axios'

/**
 * Estados de la orden de servicio (Catálogos > Estados de orden). Crear y
 * editar los hace el form con useForm() de Precognition contra la misma URL.
 */
class EstadoOrdenService {
  /** Todos, en el orden del flujo, con cuántas órdenes tiene cada uno. */
  static async getData () {
    return (await api.get('api/estados-orden')).data
  }

  static async get (id) {
    return (await api.get(`api/estados-orden/${id}`)).data
  }

  /** `ids` de arriba abajo; devuelve la lista ya ordenada. */
  static async ordenar (ids) {
    return (await api.put('api/estados-orden/ordenar', { ids })).data
  }

  static async delete (id) {
    return api.delete(`api/estados-orden/${id}`)
  }
}

export default EstadoOrdenService
