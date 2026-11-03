<template>
  <div
    :class="['brand-mark', { 'brand-mark--glow': glow }]"
    :style="{ '--mark-size': `${size}px` }"
    v-bind="a11y"
  >
    <span
      class="brand-mark__letters"
      aria-hidden="true"
    >{{ MONOGRAM }}</span>
  </div>
</template>

<script>
/**
 * El monograma de la marca, en un solo lugar. Si mañana resulta que son otras
 * letras, se cambia acá y cambia en el drawer, en el acceso y en cualquier
 * lugar futuro a la vez.
 */
export const MONOGRAM = 'NX'
</script>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  // En px. El sistema lo usa a 32 (drawer) y 42 (acceso); todo lo de adentro
  // se deriva de este número, así que no hay tamaños sueltos que ajustar.
  size: {
    type: Number,
    default: 32
  },

  // Resplandor de marca. Va sobre superficies oscuras o fotografía, donde un
  // cuadrado rojo plano se pega al fondo. Sobre superficie clara sobra.
  glow: {
    type: Boolean,
    default: false
  },

  // Nombre accesible. Vacío = decorativo, que es el caso NORMAL: en el drawer
  // y en el acceso la marca va pegada al nombre escrito en texto, y anunciar
  // las dos cosas hace que el lector de pantalla diga la marca dos veces.
  // Sólo se pasa cuando el mark va solo, sin el nombre al lado.
  label: {
    type: String,
    default: ''
  }
})

const a11y = computed(() => (
  props.label
    ? { role: 'img', 'aria-label': props.label }
    : { 'aria-hidden': 'true' }
))
</script>

<style lang="scss" scoped>
// Letras y no un SVG del monograma cursivo del logo, y es a propósito: a 32px
// una cursiva con florituras es una mancha. Un mark de app no es el logo
// achicado, es el logo redibujado para el tamaño al que se va a ver.
//
// Y letras de texto y no un <svg><text>, porque Manrope ya viene
// auto-hosteada con el proyecto: el navegador la dibuja como vectores a
// cualquier tamaño, nítida en cualquier densidad de pantalla y sin sumar un
// archivo más al bundle.
.brand-mark {
  display: flex;
  align-items: center;
  justify-content: center;
  width: var(--mark-size);
  height: var(--mark-size);

  // Radio proporcional: a 32px un radio fijo de 10px se ve correcto, pero el
  // mismo 10px en un mark de 64 se ve casi cuadrado.
  border-radius: calc(var(--mark-size) * 0.29);

  // El relleno de marca. Éste es exactamente el uso que la regla de los dos
  // rojos permite: marca = siempre relleno.
  background: $primary;

  // #FFFFFF literal, no var(--app-surface): es blanco sobre el rojo de marca,
  // y el rojo de marca no cambia con el tema. Con la variable, en oscuro
  // quedarían letras gris oscuro sobre rojo.
  color: #FFFFFF;

  flex-shrink: 0;
}

.brand-mark--glow {
  box-shadow: 0 calc(var(--mark-size) * 0.14) calc(var(--mark-size) * 0.52) rgba($primary, 0.42);
}

.brand-mark__letters {
  // 0.46 del lado: es el punto donde las dos letras llenan el cuadrado sin
  // tocar las esquinas redondeadas.
  font-size: calc(var(--mark-size) * 0.46);
  font-weight: 800;

  // Manrope deja las capitales sueltas; a este tamaño la M y la C se leen
  // como dos letras separadas en vez de como un monograma.
  letter-spacing: -0.04em;

  // Sin esto el par queda corrido a la derecha: letter-spacing agrega su
  // espacio también DESPUÉS de la última letra, así que con un valor negativo
  // la caja termina más angosta que la tinta y ésta desborda por ese lado.
  // El padding repone justo ese ancho y vuelve a centrar el par.
  padding-right: 0.04em;

  line-height: 1;
}
</style>
