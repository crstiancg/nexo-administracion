/**
 * Formatos de impresión de etiquetas. Medidas en mm.
 *
 * - A4: hojas de stickers precortadas. Las dos medidas más comunes en
 *   librerías ocupan la hoja entera (3×70 = 4×52,5 = 210 de ancho).
 * - Rollo: la ticketera térmica de 80 mm del punto de venta (imprime ~72 mm
 *   útiles). Las etiquetas salen una debajo de otra y se cortan con tijera.
 */
export const FORMATOS = [
  { id: 'a4-3x8', label: 'Hoja A4 · 3 × 8 (70 × 37 mm)', tipo: 'a4', columnas: 3, filas: 8, ancho: 70, alto: 37, padding: 2, fuente: 3 },
  { id: 'a4-4x10', label: 'Hoja A4 · 4 × 10 (52,5 × 29,7 mm)', tipo: 'a4', columnas: 4, filas: 10, ancho: 52.5, alto: 29.7, padding: 1.5, fuente: 2.6 },
  { id: 'rollo-1', label: 'Ticketera 80 mm · 1 por fila', tipo: 'rollo', columnas: 1, ancho: 72, alto: 30, padding: 2, fuente: 3.2 },
  { id: 'rollo-2', label: 'Ticketera 80 mm · 2 por fila', tipo: 'rollo', columnas: 2, ancho: 36, alto: 25, padding: 1.5, fuente: 2.5 }
]

export const A4 = { ancho: 210, alto: 297 }

// Proporción del SVG de CodigoBarras.vue (113 × 60 módulos).
const PROPORCION_CODIGO = 113 / 60

export function formatoPorId (id) {
  return FORMATOS.find((f) => f.id === id) ?? FORMATOS[0]
}

export function porHoja (formato) {
  return formato.tipo === 'a4' ? formato.columnas * formato.filas : Infinity
}

/**
 * Ancho del código de barras dentro de la etiqueta: lo más grande que entra
 * debajo de la línea de texto, sin pasarse del ancho. Más grande = más
 * fácil de leer para el lector.
 */
export function anchoCodigo (formato) {
  const altoLibre = formato.alto - 2 * formato.padding - formato.fuente * 1.25 - 0.8
  return Math.min(formato.ancho - 2 * formato.padding, altoLibre * PROPORCION_CODIGO)
}

/**
 * Una etiqueta por unidad: [{ variante, cantidad: 3 }] → 3 etiquetas.
 */
export function expandir (cola) {
  return cola.flatMap(({ variante, cantidad }) =>
    Array.from({ length: Math.max(0, Math.trunc(Number(cantidad) || 0)) }, () => variante))
}

/**
 * Reparte las etiquetas en hojas. En A4, `inicio` (1 = primera posición)
 * deja en blanco los stickers ya usados de una hoja empezada: esas celdas
 * van como null. En rollo todo va en una sola tira.
 */
export function paginar (etiquetas, formato, inicio = 1) {
  if (formato.tipo !== 'a4') return etiquetas.length ? [etiquetas] : []

  const capacidad = porHoja(formato)
  const saltear = Math.min(Math.max(1, Math.trunc(inicio) || 1), capacidad) - 1
  const celdas = [...Array(saltear).fill(null), ...etiquetas]

  if (!etiquetas.length) return []

  const hojas = []
  for (let i = 0; i < celdas.length; i += capacidad) {
    hojas.push(celdas.slice(i, i + capacidad))
  }
  return hojas
}
