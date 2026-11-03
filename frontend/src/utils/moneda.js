const formato = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' })

/**
 * "12.5" → "S/ 12.50". La API manda los decimales como string ("12.50"):
 * se convierten acá, sólo para mostrar.
 *
 * @param {string|number|null|undefined} valor
 * @returns {string} '' si no hay valor
 */
export function formatearPrecio (valor) {
  if (valor === null || valor === undefined || valor === '') return ''

  const numero = Number(valor)
  return Number.isFinite(numero) ? formato.format(numero) : ''
}
