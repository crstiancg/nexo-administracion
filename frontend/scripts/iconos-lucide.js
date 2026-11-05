/**
 * Genera src/css/lucide.scss con SÓLO los íconos Lucide del mapa de
 * src/config/iconos.js (la fuente completa de Lucide pesa varios MB).
 * Cada ícono es una máscara CSS pintada con currentColor: toma el color y
 * el tamaño del q-icon como un ícono de fuente.
 *
 *   npm run iconos
 */
import { readFileSync, writeFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { LUCIDE } from '../src/config/iconos.js'

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..')
const require = createRequire(import.meta.url)
const carpeta = join(dirname(require.resolve('lucide-static/package.json')), 'icons')
const version = require('lucide-static/package.json').version

const nombres = [...new Set(Object.values(LUCIDE))].sort()
const faltan = []
const reglas = nombres.map((nombre) => {
  let svg
  try {
    svg = readFileSync(join(carpeta, `${nombre}.svg`), 'utf8')
  } catch {
    faltan.push(nombre)
    return ''
  }
  const limpio = svg
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/\s+class="[^"]*"/, '')
    .replace(/\s*\n\s*/g, ' ')
    .replace(/>\s+</g, '><')
    .trim()
  const url = `url("data:image/svg+xml,${encodeURIComponent(limpio).replace(/'/g, '%27')}")`
  return `.lucide-${nombre} { --lucide: ${url}; }`
})

if (faltan.length) {
  console.error(`No existen en lucide-static ${version}: ${faltan.join(', ')}`)
  process.exit(1)
}

writeFileSync(join(raiz, 'src/css/lucide.scss'), `// GENERADO por scripts/iconos-lucide.js (npm run iconos): no editar a mano.
// Íconos Lucide ${version} (ISC) usados por src/config/iconos.js.

.lucide {
  background-color: currentColor;
  -webkit-mask: var(--lucide) center / contain no-repeat;
  mask: var(--lucide) center / contain no-repeat;
}

${reglas.join('\n')}
`)
console.log(`src/css/lucide.scss: ${nombres.length} íconos`)
