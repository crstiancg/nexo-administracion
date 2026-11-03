/**
 * Estados: label, status del AppChip e ícono nacen juntos (mismo criterio que
 * Inventario), así nunca se desincronizan.
 */
export const ESTADOS = {
  pendiente: { label: 'Pendiente', status: 'warning' },
  confirmado: { label: 'Confirmado', status: 'info' },
  entregado: { label: 'Entregado', status: 'positive' },
  cancelado: { label: 'Cancelado', status: 'negative' },
  // Venta ya hecha que se deshizo (volvieron stock y plata).
  anulado: { label: 'Anulado', status: 'negative' }
}

// Mismas claves que Pedido::CANALES del backend.
export const CANALES = [
  { value: 'mostrador', label: 'Mostrador' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'redes', label: 'Redes sociales' },
  { value: 'web', label: 'Web' },
  // Lo pone el sistema al convertir una cotización.
  { value: 'cotizacion', label: 'Cotización' }
]
