/**
 * IGV (18%), incluido en todos los precios: el total es lo que paga el
 * cliente y se desglosa en operación gravada + IGV. Misma cuenta que
 * App\Support\Igv del backend (que es el que guarda el desglose de cada
 * documento); acá sólo se usa para las vistas previas antes de guardar.
 */
export const TASA_IGV = 0.18

export const ETIQUETA_IGV = `IGV (${TASA_IGV * 100}%)`

function redondear (n) {
  return Math.round(n * 100) / 100
}

/**
 * @param {number|string|null} total
 * @returns {{ opGravada: number, igv: number }}
 */
export function desglosarIgv (total) {
  const t = redondear(Number(total) || 0)
  const opGravada = redondear(t / (1 + TASA_IGV))
  return { opGravada, igv: redondear(t - opGravada) }
}

/**
 * El desglose guardado de un documento (pedido, cotización, compra) o, si
 * todavía no lo trae, calculado desde su total.
 */
export function desgloseDe (documento) {
  if (documento?.op_gravada !== undefined && documento?.igv !== undefined) {
    return { opGravada: Number(documento.op_gravada), igv: Number(documento.igv) }
  }
  return desglosarIgv(documento?.total)
}
