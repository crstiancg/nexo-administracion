/**
 * Conversión entre lo que muestra un <input type="datetime-local"> (hora
 * local SIN zona: "2026-12-15T23:59") y lo que viaja a la API (ISO CON zona).
 *
 * La base guarda en UTC: si la fecha viajara sin zona, el backend la tomaría
 * como UTC y en Lima quedaría corrida 5 horas.
 */

function dosDigitos (n) {
  return String(n).padStart(2, '0')
}

/**
 * ISO de la API → valor para el input, en hora local.
 * @param {string|null} iso
 */
export function isoALocal (iso) {
  if (!iso) return ''
  const d = new Date(iso)
  return `${d.getFullYear()}-${dosDigitos(d.getMonth() + 1)}-${dosDigitos(d.getDate())}T${dosDigitos(d.getHours())}:${dosDigitos(d.getMinutes())}`
}

/**
 * Valor del input (hora local) → ISO con zona para la API. '' si está vacío
 * (lo marca la validación del backend).
 * @param {string} local
 */
export function localAIso (local) {
  if (!local) return ''
  const d = new Date(local)
  return Number.isNaN(d.getTime()) ? '' : d.toISOString()
}

const formato = new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short' })

export function formatearFechaHora (iso) {
  return iso ? formato.format(new Date(iso)) : ''
}
