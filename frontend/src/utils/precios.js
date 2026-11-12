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

/**
 * El tramo de la tabla de precios de referencia (Configuración) que le toca a
 * un costo: el primero cuyo "hasta" lo alcanza (incluido), o el último sin
 * tope. Sin tabla, o con un costo mayor al último tope, no hay tramo.
 *
 * @param {number|string} costo  costo de compra por unidad
 * @param {{ hasta: number|null, porcentaje: number }[]} margenes
 * @returns {{ hasta: number|null, porcentaje: number }|null}
 */
export function tramoDe (costo, margenes) {
  const c = Number(costo)
  if (!(c > 0) || !margenes?.length) return null
  return margenes.find((t) => t.hasta === null || t.hasta === undefined || c <= Number(t.hasta)) ?? null
}

/**
 * Precio de venta sugerido: el costo más el % de su tramo.
 * 80 con 40 % → { precio: '112.00', porcentaje: 40 }.
 *
 * @param {number|string} costo
 * @param {{ hasta: number|null, porcentaje: number }[]} margenes
 * @returns {{ precio: string, porcentaje: number }|null}
 */
export function precioSugerido (costo, margenes) {
  const tramo = tramoDe(costo, margenes)
  if (!tramo) return null
  const porcentaje = Number(tramo.porcentaje)
  // En céntimos, para que 0.1 + 0.2 no deje 0.30000000000000004.
  const precio = Math.round(Number(costo) * (100 + porcentaje)) / 100
  return { precio: precio.toFixed(2), porcentaje }
}
