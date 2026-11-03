/**
 * Mismas reglas que StoreColorRequest::normalizarHex del backend: acepta
 * "ff0000", "#F00" o "#ff0000" y devuelve "#FF0000". Si no es un hexadecimal
 * válido devuelve null (para no pintar un color que no es el que se guarda).
 *
 * @param {string|null|undefined} valor
 * @returns {string|null}
 */
export function normalizarHex (valor) {
  let hex = String(valor ?? '').trim().replace(/^#/, '').toUpperCase()

  if (/^[0-9A-F]{3}$/.test(hex)) {
    hex = hex.replace(/(.)/g, '$1$1')
  }

  return /^[0-9A-F]{6}$/.test(hex) ? `#${hex}` : null
}
