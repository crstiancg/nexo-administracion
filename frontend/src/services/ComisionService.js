import { api } from '@/boot/axios'

/**
 * Comisiones del período (técnicos y vendedores), sus porcentajes y el pago.
 */
class ComisionService {
  // params: { desde, hasta } (YYYY-MM-DD). Devuelve { desde, hasta, data }.
  static async get (params) {
    return (await api.get('api/comisiones', { params })).data
  }

  // empleados: [{ id, reparacion, venta }]
  static async porcentajes (empleados, params) {
    return (await api.put('api/comisiones/porcentajes', { empleados }, { params })).data
  }

  // pago: { metodo, de_caja, referencia }
  static async pagar (userId, pago, params) {
    return (await api.post('api/comisiones/pagar', { user_id: userId, pago }, { params })).data
  }
}

export default ComisionService
