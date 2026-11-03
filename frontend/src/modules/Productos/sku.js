/**
 * SKU sugerido de una presentación: "Tinta 544" + "Botella 65 ml" +
 * color "Gris" → "SIKA-1A-CAR300ML-GRI". Es sólo una sugerencia: el form lo
 * deja editar y el backend valida que sea único y de letras, números y guiones.
 */

// Sin tildes, en mayúsculas y con cualquier otro símbolo como espacio.
function limpiar (texto) {
  return String(texto ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, ' ')
    .trim()
}

function palabras (texto) {
  return limpiar(texto).split(' ').filter(Boolean)
}

// Primera palabra hasta 4 letras, segunda hasta 3: "SIKA-1A".
function parteProducto (nombre) {
  return palabras(nombre)
    .slice(0, 2)
    .map((palabra, i) => palabra.slice(0, i === 0 ? 4 : 3))
    .join('-')
}

// "Botella 65 ml" → "BOT65ML", "Caja 305 m" → "CAJ305M": el envase
// abreviado y la medida completa (es lo que distingue una de otra).
function partePresentacion (presentacion) {
  const [envase = '', ...resto] = palabras(presentacion)
  return (/^[0-9]/.test(envase) ? envase : envase.slice(0, 3)) + resto.join('')
}

// "Gris" → "GRI", "Gris claro" → "GRIC": sin la inicial de la segunda
// palabra, "Gris" y "Gris claro" darían el mismo SKU.
function parteColor (nombre) {
  const [primera = '', segunda = ''] = palabras(nombre)
  return primera.slice(0, 3) + segunda.slice(0, 1)
}

/**
 * @param {string} producto nombre del producto
 * @param {string} presentacion "Caja 305 m"
 * @param {string} [color] nombre del color (opcional)
 * @returns {string} '' si todavía no hay datos para armarlo
 */
export function sugerirSku (producto, presentacion, color) {
  return [parteProducto(producto), partePresentacion(presentacion), parteColor(color)]
    .filter(Boolean)
    .join('-')
}
