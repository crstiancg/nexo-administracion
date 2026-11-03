/**
 * Codificación EAN-13 a módulos (barras de 1 = negro, 0 = blanco), para
 * dibujarla en SVG sin depender de una librería. Son 95 módulos:
 * guarda 101 · 6 dígitos izquierdos · guarda central 01010 · 6 derechos · 101.
 *
 * El primer dígito no se dibuja: se codifica en la combinación de juegos
 * L/G con que se escriben los 6 de la izquierda.
 */

// Juego L. El R es L invertido y el G es R al revés.
const L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011']
const R = L.map((bits) => [...bits].map((b) => (b === '1' ? '0' : '1')).join(''))
const G = R.map((bits) => [...bits].reverse().join(''))

const PARIDAD = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL']

export function digitoVerificador (doceDigitos) {
  const suma = [...doceDigitos].reduce((acc, d, i) => acc + Number(d) * (i % 2 === 0 ? 1 : 3), 0)
  return (10 - (suma % 10)) % 10
}

export function esEan13 (codigo) {
  return /^\d{13}$/.test(codigo ?? '') && digitoVerificador(codigo.slice(0, 12)) === Number(codigo[12])
}

/**
 * @param {string} codigo 13 dígitos con verificador válido
 * @returns {string} 95 caracteres '0'/'1'
 */
export function modulosEan13 (codigo) {
  if (!esEan13(codigo)) throw new Error(`EAN-13 inválido: ${codigo}`)

  const d = [...codigo].map(Number)
  const paridad = PARIDAD[d[0]]
  const izquierda = d.slice(1, 7).map((n, i) => (paridad[i] === 'L' ? L : G)[n]).join('')
  const derecha = d.slice(7).map((n) => R[n]).join('')

  return `101${izquierda}01010${derecha}101`
}

/**
 * Las barras negras como tramos {x, ancho} en módulos, marcando las guardas
 * (que se dibujan más largas, como en cualquier etiqueta de supermercado).
 */
export function barrasEan13 (codigo) {
  const modulos = modulosEan13(codigo)
  const esGuarda = (x) => x < 3 || (x >= 45 && x < 50) || x >= 92
  const barras = []

  for (let x = 0; x < modulos.length; x++) {
    if (modulos[x] !== '1') continue
    const ultima = barras.at(-1)
    if (ultima && ultima.x + ultima.ancho === x && ultima.guarda === esGuarda(x)) {
      ultima.ancho++
    } else {
      barras.push({ x, ancho: 1, guarda: esGuarda(x) })
    }
  }

  return barras
}
