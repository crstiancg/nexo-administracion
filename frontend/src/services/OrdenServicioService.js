import { api } from '@/boot/axios'

/**
 * Crear y editar no pasan por acá: los hace el form con useForm() de
 * Precognition. Los cambios de estado sí. Los abonos van por el pedido de la
 * orden (PagoForm, api/pedidos/{id}/pagos).
 */
class OrdenServicioService {
  static async getData (config) {
    return (await api.get('api/ordenes-servicio', config)).data
  }

  static async get (id) {
    return (await api.get(`api/ordenes-servicio/${id}`)).data
  }

  // [{ estado, label, total }] en el orden del flujo.
  static async resumen (config) {
    return (await api.get('api/ordenes-servicio/resumen', config)).data
  }

  static async tecnicos () {
    return (await api.get('api/ordenes-servicio/tecnicos')).data
  }

  static async cambiarEstado (id, estado, nota) {
    return (await api.post(`api/ordenes-servicio/${id}/estado`, { estado, nota: nota || null })).data
  }

  // { orden, etiqueta }: enlaces firmados (unos minutos) a los documentos impresos.
  static async impresion (id) {
    return (await api.post(`api/ordenes-servicio/${id}/impresion`)).data
  }

  // Página pública (sin sesión): lo que ve el cliente al escanear el QR.
  static async seguimiento (token) {
    return (await api.get(`api/seguimiento/${token}`)).data
  }
}

export default OrdenServicioService
