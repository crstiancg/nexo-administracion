/**
 * Estados: label y status del AppChip nacen juntos.
 */
export const ESTADOS = {
  pendiente: { label: 'Pendiente', status: 'warning' },
  enviada: { label: 'Enviado al proveedor', status: 'info' },
  recibida: { label: 'Recibido', status: 'positive' },
  anulada: { label: 'Anulado', status: 'negative' }
}

export { fechaCorta, hoyLocal } from '@/modules/Cotizaciones/constantes'
