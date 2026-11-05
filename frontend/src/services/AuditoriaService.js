import { api } from '@/boot/axios'

// Sólo lectura: quién cambió qué y cuándo (con los filtros de la pantalla).
class AuditoriaService {
  static async getData (config) {
    return (await api.get('api/auditoria', config)).data
  }
}

export default AuditoriaService
