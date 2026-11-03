import { api } from '@/boot/axios'

/**
 * Créditos a cuotas. Las cuotas se cobran como cualquier pedido
 * (PagoForm → api/pedidos/{id}/pagos).
 */
class CreditoService {
  // Devuelve además `resumen: { por_cobrar, vencido, activos, con_vencidas }`.
  static async getData (config) {
    return (await api.get('api/creditos', config)).data
  }

  static async get (id) {
    return (await api.get(`api/creditos/${id}`)).data
  }

  // { pedido_id, frecuencia, numero_cuotas, primer_vencimiento, observacion }
  static async otorgar (credito) {
    return (await api.post('api/creditos', { credito })).data
  }
}

export default CreditoService
