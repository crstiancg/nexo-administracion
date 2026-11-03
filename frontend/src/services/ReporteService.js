import { api } from '@/boot/axios'

/**
 * Reportes de sólo lectura. Filtros: desde, hasta (YYYY-MM-DD) y sede_id
 * (vacío = todas).
 */
class ReporteService {
  static async ventas (params) {
    return (await api.get('api/reportes/ventas', { params })).data
  }

  static async productos (params) {
    return (await api.get('api/reportes/productos', { params })).data
  }

  static async inventario (params) {
    return (await api.get('api/reportes/inventario', { params })).data
  }
}

export default ReporteService
