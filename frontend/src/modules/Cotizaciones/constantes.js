/**
 * Estados: label y status del AppChip nacen juntos. "vencida" la calcula el
 * backend (pendiente con la validez pasada).
 */
export const ESTADOS = {
  pendiente: { label: 'Pendiente', status: 'warning' },
  vencida: { label: 'Vencida', status: 'negative' },
  convertida: { label: 'Convertida en pedido', status: 'positive' },
  rechazada: { label: 'Rechazada', status: 'info' }
}

/**
 * Fecha local YYYY-MM-DD (no UTC: a las 9 p. m. en Lima, toISOString ya es
 * "mañana"), corrida `dias` días.
 */
export function hoyLocal (dias = 0) {
  const d = new Date()
  d.setDate(d.getDate() + dias)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

/** "2026-10-03" → "03/10/2026". */
export function fechaCorta (iso) {
  return iso ? iso.slice(0, 10).split('-').reverse().join('/') : ''
}
