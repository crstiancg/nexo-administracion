/**
 * Qué precio le toca a una presentación según el cliente.
 *
 * - Cliente mayorista (empresa) y la presentación tiene precio por mayor →
 *   el por mayor. Si una oferta deja el precio de hoy todavía más bajo, se
 *   respeta la oferta (nunca se le cobra más al mayorista que al público).
 * - Cualquier otro caso → el precio de hoy (el de lista o el de oferta).
 *
 * @param {number|string} precioHoy  el de venta al público hoy
 * @param {number|string|null} precioMayor  null = la presentación no tiene
 * @param {{ mayorista?: boolean }|null} cliente
 * @returns {{ precio: string, porMayor: boolean }}
 */
export function precioPara (precioHoy, precioMayor, cliente) {
  const hoy = Number(precioHoy ?? 0)
  const mayor = precioMayor === null || precioMayor === undefined || precioMayor === '' ? null : Number(precioMayor)

  if (cliente?.mayorista && mayor !== null && mayor < hoy) {
    return { precio: mayor.toFixed(2), porMayor: true }
  }
  return { precio: hoy.toFixed(2), porMayor: false }
}
