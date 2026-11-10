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

  // Sin caja abierta: la anterior si se cerró sola sin arqueo
  // ({ id, abierta_at, monto_esperado }) o null.
  static async anteriorSinArqueo () {
    return (await api.get('api/cajas/actual')).data.anterior ?? null
  }

  // Al iniciar sesión: { abrir, sede } (sólo con cajas.abrir-al-ingresar).
  static async alIngresar () {
    return (await api.get('api/cajas/al-ingresar')).data
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
