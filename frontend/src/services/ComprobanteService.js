import { api } from '@/boot/axios'

/**
 * Comprobantes electrónicos (boletas y facturas SUNAT) de los pedidos.
 * Tipos: '03' boleta, '01' factura.
 */
class ComprobanteService {
  static async getData (config) {
    return (await api.get('api/comprobantes', config)).data
  }

  // Emite el comprobante de un pedido ya vendido (confirmado o entregado).
  // `clienteId`: a quién, si no es el cliente del pedido (queda en el pedido).
  static async emitir (pedidoId, tipo, clienteId = null) {
    return (await api.post(`api/pedidos/${pedidoId}/comprobantes`, { tipo, ...(clienteId ? { cliente_id: clienteId } : {}) })).data
  }

  // Reenvía el mismo comprobante (mismo número) que no llegó a SUNAT.
  static async reenviar (id) {
    return (await api.post(`api/comprobantes/${id}/reenviar`)).data
  }

  /**
   * Descarga el XML firmado o el CDR (respuesta de SUNAT). Con el token: por
   * eso va por axios y no con un enlace directo.
   *
   * @param {'xml'|'cdr'} archivo
   */
  static async descargar (comprobante, archivo) {
    const { data } = await api.get(`api/comprobantes/${comprobante.id}/${archivo}`, { responseType: 'blob' })
    const url = URL.createObjectURL(data)
    const enlace = document.createElement('a')
    enlace.href = url
    enlace.download = archivo === 'xml' ? `${comprobante.serie}-${comprobante.correlativo}.xml` : `R-${comprobante.serie}-${comprobante.correlativo}.zip`
    enlace.click()
    URL.revokeObjectURL(url)
  }
}

export default ComprobanteService
