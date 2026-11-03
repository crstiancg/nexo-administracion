// Mismas claves que Pago::METODOS del backend.
export const METODOS = [
  { value: 'efectivo', label: 'Efectivo', icon: 'payments' },
  { value: 'yape', label: 'Yape', icon: 'qr_code_2' },
  { value: 'plin', label: 'Plin', icon: 'qr_code_2' },
  { value: 'transferencia', label: 'Transferencia', icon: 'account_balance' },
  { value: 'tarjeta', label: 'Tarjeta', icon: 'credit_card' }
]

// Los que se verifican contra el celular o el banco: piden n° de operación
// (igual que CobrarPedidoRequest::CON_OPERACION).
export const CON_OPERACION = ['yape', 'plin', 'transferencia']

// Por si la caja no trae su hora (config app.hora_aviso_cierre del backend):
// desde esta hora el punto de venta avisa que hay que cerrarla. A medianoche
// el backend la cierra sola, sin arqueo.
export const HORA_AVISO_CIERRE = 23

// Notificación tras el cierre: el arqueo dice si cuadró.
export function avisoCierre (resultado, formatearPrecio) {
  const diferencia = Number(resultado?.diferencia ?? 0)
  return {
    type: diferencia === 0 ? 'positive' : 'warning',
    message: diferencia === 0
      ? 'Caja cerrada: cuadra exacto.'
      : `Caja cerrada con ${diferencia < 0 ? 'faltante' : 'sobrante'} de ${formatearPrecio(Math.abs(diferencia))}.`,
    position: 'top-right',
    timeout: 4000
  }
}
