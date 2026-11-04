import { Dark } from 'quasar'
import { reactive, watch } from 'vue'

/**
 * Tema elegible por cada usuario (menú de la paleta en la barra superior):
 * color de marca, fuente y modo. Se guarda en el navegador, no en el
 * servidor: es preferencia de quien mira, no de la empresa.
 *
 * El color pisa --q-primary en <html>; app.scss deriva de ahí el hover y los
 * tintes con color-mix, y Quasar lo usa para todo lo que es color="primary".
 * Igual que Nuxt UI: la paleta de Tailwind, tono 500 en claro (con texto
 * blanco, o negro en los colores claros) y tono 400 en oscuro con texto
 * negro encima. Son los tonos vivos; los 700 se leían apagados.
 * `oscuro` = tono para el modo oscuro; `texto` = texto sobre el relleno en
 * claro; `textoOscuro` = en oscuro (por defecto casi negro).
 */
const NEGRO = '#0A0A0A'

export const COLORES = [
  { value: 'rojo', label: 'Rojo Nexo', hex: '#D40001', oscuro: '#F87171' },
  { value: 'negro', label: 'Negro', hex: '#140000', oscuro: '#F2F2F2', textoOscuro: '#140000' },
  { value: 'red', label: 'Rojo', hex: '#EF4444', oscuro: '#F87171' },
  { value: 'naranja', label: 'Naranja', hex: '#F97316', oscuro: '#FB923C' },
  { value: 'ambar', label: 'Ámbar', hex: '#F59E0B', oscuro: '#FBBF24', texto: NEGRO },
  { value: 'amarillo', label: 'Amarillo', hex: '#EAB308', oscuro: '#FACC15', texto: NEGRO },
  { value: 'lima', label: 'Lima', hex: '#84CC16', oscuro: '#A3E635', texto: NEGRO },
  { value: 'verde', label: 'Verde', hex: '#22C55E', oscuro: '#4ADE80' },
  { value: 'esmeralda', label: 'Esmeralda', hex: '#10B981', oscuro: '#34D399' },
  { value: 'turquesa', label: 'Turquesa', hex: '#14B8A6', oscuro: '#2DD4BF' },
  { value: 'cian', label: 'Cian', hex: '#06B6D4', oscuro: '#22D3EE' },
  { value: 'celeste', label: 'Celeste', hex: '#0EA5E9', oscuro: '#38BDF8' },
  { value: 'azul', label: 'Azul', hex: '#3B82F6', oscuro: '#60A5FA' },
  { value: 'indigo', label: 'Índigo', hex: '#6366F1', oscuro: '#818CF8' },
  { value: 'violeta', label: 'Violeta', hex: '#8B5CF6', oscuro: '#A78BFA' },
  { value: 'purpura', label: 'Púrpura', hex: '#A855F7', oscuro: '#C084FC' },
  { value: 'fucsia', label: 'Fucsia', hex: '#D946EF', oscuro: '#E879F9' },
  { value: 'rosa', label: 'Rosa', hex: '#EC4899', oscuro: '#F472B6' },
  { value: 'rose', label: 'Rosado', hex: '#F43F5E', oscuro: '#FB7185' }
]

// Sólo fuentes ya empaquetadas (o la del sistema): nada se baja de afuera.
export const FUENTES = [
  { value: 'dm-sans', label: 'DM Sans', css: "'DM Sans Variable', 'DM Sans', sans-serif" },
  { value: 'manrope', label: 'Manrope', css: "'Manrope Variable', 'Manrope', sans-serif" },
  { value: 'sistema', label: 'Del sistema', css: "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif" }
]

export const MODOS = [
  { value: 'claro', label: 'Claro', icon: 'light_mode' },
  { value: 'oscuro', label: 'Oscuro', icon: 'dark_mode' },
  { value: 'sistema', label: 'Sistema', icon: 'desktop_windows' }
]

const CLAVE = 'nexo.tema'
const DEFECTO = { color: 'rojo', fuente: 'dm-sans', modo: 'claro' }

function leer () {
  try {
    return JSON.parse(localStorage.getItem(CLAVE)) ?? {}
  } catch {
    return {}
  }
}

export const TEMA = reactive({ ...DEFECTO, ...leer() })

function aplicarModo () {
  Dark.set(TEMA.modo === 'sistema' ? 'auto' : TEMA.modo === 'oscuro')
}

export function aplicarTema () {
  const color = COLORES.find((c) => c.value === TEMA.color) ?? COLORES[0]
  const fuente = FUENTES.find((f) => f.value === TEMA.fuente) ?? FUENTES[0]
  const oscuro = Dark.isActive
  const raiz = document.documentElement.style

  raiz.setProperty('--q-primary', (oscuro && color.oscuro) || color.hex)
  raiz.setProperty('--app-on-primary', oscuro ? (color.textoOscuro ?? NEGRO) : (color.texto ?? '#FFFFFF'))
  raiz.setProperty('--app-font', fuente.css)
}

export function restablecerTema () {
  Object.assign(TEMA, DEFECTO)
}

/** Lo llama el boot: aplica lo guardado y sigue los cambios. */
export function iniciarTema () {
  aplicarModo()
  aplicarTema()

  // El modo "sistema" cambia solo cuando cambia el del equipo.
  watch(() => Dark.isActive, aplicarTema)
  watch(TEMA, () => {
    localStorage.setItem(CLAVE, JSON.stringify({ ...TEMA }))
    aplicarModo()
    aplicarTema()
  })
}
