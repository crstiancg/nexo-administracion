/**
 * Utilidades para el árbol de categorías a partir de la lista plana que
 * devuelve la API ({ id, nombre, parent_id }).
 */

export const SEPARADOR = ' › '

/**
 * "Ropa › Niños › Polos". Corta si encuentra un ciclo (no debería haber: el
 * backend los rechaza) para no colgar la pantalla.
 *
 * @param {{ id: number, nombre: string, parent_id: number|null }} categoria
 * @param {Map<number, object>} porId
 */
export function rutaDe (categoria, porId) {
  const nombres = []
  const vistos = new Set()
  let actual = categoria

  while (actual && !vistos.has(actual.id)) {
    vistos.add(actual.id)
    nombres.unshift(actual.nombre)
    actual = porId.get(actual.parent_id)
  }

  return nombres.join(SEPARADOR)
}

/**
 * Ids de la categoría y de todas sus subcategorías, a cualquier profundidad.
 *
 * @param {number} id
 * @param {object[]} lista
 * @returns {Set<number>}
 */
export function conDescendientes (id, lista) {
  const ids = new Set([id])
  let nivel = [id]

  while (nivel.length) {
    nivel = lista.filter((c) => nivel.includes(c.parent_id) && !ids.has(c.id)).map((c) => c.id)
    nivel.forEach((hijo) => ids.add(hijo))
  }

  return ids
}

/**
 * Opciones del select "Categoría padre", con la ruta completa como etiqueta.
 * Al editar se excluyen la propia categoría y sus subcategorías: elegirlas
 * dejaría el árbol en un ciclo (el backend también lo valida).
 *
 * @param {object[]} lista
 * @param {number|null} editandoId
 * @returns {{ value: number, label: string }[]}
 */
export function opcionesPadre (lista, editandoId = null) {
  const porId = new Map(lista.map((c) => [c.id, c]))
  const excluidos = editandoId ? conDescendientes(editandoId, lista) : new Set()

  return lista
    .filter((c) => !excluidos.has(c.id))
    .map((c) => ({ value: c.id, label: rutaDe(c, porId) }))
    .sort((a, b) => a.label.localeCompare(b.label, 'es'))
}
