import { api } from '@/boot/axios'

/**
 * Ingresos y gastos que no son ventas, sus conceptos y las ganancias.
 */
class FinanzaService {
  // Devuelve además `totales: { ingresos, gastos }` del filtro completo.
  static async movimientos (config) {
    return (await api.get('api/movimientos-financieros', config)).data
  }

  static async registrar (movimiento) {
    return (await api.post('api/movimientos-financieros', { movimiento })).data
  }

  static async eliminar (id) {
    return api.delete(`api/movimientos-financieros/${id}`)
  }

  static async conceptos (config) {
    return (await api.get('api/conceptos-financieros', config)).data
  }

  static async guardarConcepto (concepto) {
    return concepto.id
      ? (await api.put(`api/conceptos-financieros/${concepto.id}`, { concepto })).data
      : (await api.post('api/conceptos-financieros', { concepto })).data
  }

  static async eliminarConcepto (id) {
    return api.delete(`api/conceptos-financieros/${id}`)
  }

  // { desde, hasta } (YYYY-MM-DD); sin fechas, el mes en curso.
  static async ganancias (params) {
    return (await api.get('api/ganancias', { params })).data
  }
}

export default FinanzaService
