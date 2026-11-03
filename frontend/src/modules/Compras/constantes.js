/**
 * Estados: label y status del AppChip nacen juntos (mismo criterio que
 * Pedidos e Inventario).
 */
export const ESTADOS = {
  registrada: { label: 'Registrada', status: 'positive' },
  anulada: { label: 'Anulada', status: 'negative' }
}

// Mismas claves que Compra::TIPOS_DOCUMENTO del backend.
export const TIPOS_DOCUMENTO = [
  { value: 'factura', label: 'Factura' },
  { value: 'boleta', label: 'Boleta' },
  { value: 'guia', label: 'Guía de remisión' },
  { value: 'otro', label: 'Otro' }
]

/** "2026-10-03" → "03/10/2026". */
export function fechaCorta (iso) {
  return iso ? iso.slice(0, 10).split('-').reverse().join('/') : ''
}
