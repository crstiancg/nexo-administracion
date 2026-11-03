/**
 * Cantidades de stock: hasta 3 decimales (kg, metros), sin ceros de más.
 * 12 → "12", 2.5 → "2.5", 0.125 → "0.125". null/'' → null.
 *
 * @param {number|string|null} valor
 * @returns {string|null}
 */
export function formatearCantidad (valor) {
  if (valor === null || valor === undefined || valor === '') return null
  const n = Number(valor)
  if (!Number.isFinite(n)) return null
  return String(Math.round(n * 1000) / 1000)
}
