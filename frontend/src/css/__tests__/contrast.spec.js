import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

// Los tokens se LEEN del SCSS, no se copian acá. Si alguien cambia un valor
// en quasar.variables.scss, este test mide el valor nuevo automáticamente.
// Duplicarlos en JS sería garantizar que un día divergen.
//
// Se resuelve desde la raíz del proyecto y no con import.meta.url, porque
// bajo Vitest los módulos se sirven por HTTP y esa URL no es file:.
const SCSS = readFileSync(
  resolve(process.cwd(), 'src/css/quasar.variables.scss'),
  'utf8'
)

function token (name) {
  const match = SCSS.match(new RegExp(`^\\$${name}\\s*:\\s*(#[0-9A-Fa-f]{6})`, 'm'))
  if (!match) throw new Error(`No encontré el token $${name} en quasar.variables.scss`)
  return match[1]
}

function rgb (hex) {
  return [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16))
}

// Equivalente de mix() de Sass: interpolación lineal en sRGB. El peso es
// cuánto del PRIMER color entra.
function mix (hexA, hexB, weight) {
  const a = rgb(hexA)
  const b = rgb(hexB)
  const w = weight / 100
  const out = a.map((channel, i) => Math.round(channel * w + b[i] * (1 - w)))
  return `#${out.map((c) => c.toString(16).padStart(2, '0')).join('')}`
}

function luminance (hex) {
  const [r, g, b] = rgb(hex).map((channel) => {
    const c = channel / 255
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  })
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function contrast (fg, bg) {
  const light = Math.max(luminance(fg), luminance(bg))
  const dark = Math.min(luminance(fg), luminance(bg))
  return (light + 0.05) / (dark + 0.05)
}

const AA_TEXT = 4.5
const AA_LARGE = 3

// ── tokens ───────────────────────────────────────────────────────────────
const PRIMARY = token('primary')
const PRIMARY_HOVER = token('primary-hover')
const PRIMARY_SOFT = token('primary-soft')
const SURFACE = token('surface')
const PAGE = token('page-background')
const INK = token('text-primary')
const INK_2 = token('text-secondary')

// $dark y $dark-page son las variables de Quasar, apuntadas a nuestra paleta:
// una sola familia de oscuro para sus componentes y los nuestros.
const DARK_SURFACE = token('dark')
const DARK_PAGE = token('dark-page')
const DARK_INK = token('dark-text-primary')
const DARK_INK_2 = token('dark-text-secondary')

const STATUSES = {
  positive: token('positive'),
  warning: token('warning'),
  info: token('info'),
  negative: token('negative')
}

// Réplicas de las funciones de quasar.variables.scss.
const statusInk = (c) => mix(c, INK, 45)
const statusTint = (c) => mix(c, SURFACE, 10)
const statusInkDark = (c) => mix(c, '#ffffff', 55)
const statusTintDark = (c) => mix(c, DARK_SURFACE, 18)

describe('Contraste — tema claro', () => {
  it.each([
    ['tinta sobre superficie', INK, SURFACE],
    ['tinta sobre página', INK, PAGE],
    ['tinta secundaria sobre superficie', INK_2, SURFACE],
    ['tinta secundaria sobre página', INK_2, PAGE],
    ['tinta de marca sobre tinte de marca', PRIMARY_HOVER, PRIMARY_SOFT]
  ])('%s pasa AA', (_label, fg, bg) => {
    expect(contrast(fg, bg)).toBeGreaterThanOrEqual(AA_TEXT)
  })

  it.each(Object.keys(STATUSES))('el chip %s pasa AA sobre su tinte', (name) => {
    const color = STATUSES[name]
    expect(contrast(statusInk(color), statusTint(color))).toBeGreaterThanOrEqual(AA_TEXT)
  })

  it.each(Object.keys(STATUSES))('el delta %s pasa AA sobre superficie', (name) => {
    expect(contrast(statusInk(STATUSES[name]), SURFACE)).toBeGreaterThanOrEqual(AA_TEXT)
  })
})

describe('Contraste — tema oscuro', () => {
  it.each([
    ['tinta sobre superficie', DARK_INK, DARK_SURFACE],
    ['tinta sobre página', DARK_INK, DARK_PAGE],
    ['tinta secundaria sobre superficie', DARK_INK_2, DARK_SURFACE],
    ['tinta secundaria sobre página', DARK_INK_2, DARK_PAGE]
  ])('%s pasa AA', (_label, fg, bg) => {
    expect(contrast(fg, bg)).toBeGreaterThanOrEqual(AA_TEXT)
  })

  it.each(Object.keys(STATUSES))('el chip %s pasa AA sobre su tinte oscuro', (name) => {
    const color = STATUSES[name]
    expect(contrast(statusInkDark(color), statusTintDark(color))).toBeGreaterThanOrEqual(AA_TEXT)
  })

  it.each(Object.keys(STATUSES))('el delta %s pasa AA sobre superficie oscura', (name) => {
    expect(contrast(statusInkDark(STATUSES[name]), DARK_SURFACE)).toBeGreaterThanOrEqual(AA_TEXT)
  })

  it('la tinta de marca se aclara y pasa AA sobre el tinte oscuro', () => {
    // La razón de status-ink-dark: la fórmula clara oscurece, y sobre fondo
    // oscuro eso da texto negro sobre negro.
    const ink = mix(PRIMARY, '#ffffff', 55)
    const tint = mix(PRIMARY, DARK_SURFACE, 20)
    expect(contrast(ink, tint)).toBeGreaterThanOrEqual(AA_TEXT)
  })
})

describe('Contraste — el relleno de marca, en los dos temas', () => {
  it('blanco sobre $primary-hover pasa AA', () => {
    expect(contrast('#ffffff', PRIMARY_HOVER)).toBeGreaterThanOrEqual(AA_TEXT)
  })

  it('blanco sobre $primary NO llega a AA de texto normal', () => {
    // LIMITACIÓN CONOCIDA Y MEDIDA: el azul de marca da ~3.68:1 con texto
    // blanco. Alcanza para AA de texto grande y para componentes de UI (3:1),
    // pero NO para texto normal (4.5:1), y el label del botón mide 13.5px.
    //
    // Se arregla usando $primary-hover como relleno de reposo (6.70:1) —
    // decisión de marca, no técnica, así que queda documentada acá con su
    // número en vez de escondida.
    const ratio = contrast('#ffffff', PRIMARY)
    expect(ratio).toBeGreaterThanOrEqual(AA_LARGE)
    expect(ratio).toBeLessThan(AA_TEXT)
  })
})
