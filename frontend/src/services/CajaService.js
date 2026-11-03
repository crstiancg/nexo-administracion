import { api } from '@/boot/axios'

/**
 * Abrir, cerrar, registrar movimientos y cobrar no pasan por acá: los hacen
 * los forms con useForm() de Precognition, que valida y envía.
 */
class CajaService {
  // La caja abierta con sus totales, o null si no hay.
  static async actual () {
    return (await api.get('api/cajas/actual')).data.caja
  }

  // Historial de cajas.
  static async getData (config) {
    return (await api.get('api/cajas', config)).data
  }

  static async get (id) {
    return (await api.get(`api/cajas/${id}`)).data
  }
}

export default CajaService
