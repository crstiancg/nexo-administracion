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

  // Kardex valorizado de una presentación (variante_id, sede_id, desde, hasta).
  static async kardex (params) {
    return (await api.get('api/reportes/kardex', { params })).data
  }

  static async inventario (params) {
    return (await api.get('api/reportes/inventario', { params })).data
  }
}

export default ReporteService
