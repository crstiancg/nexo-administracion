<template>
  <!-- Vectorial: se imprime nítido a cualquier tamaño. El ancho lo pone quien
       lo usa; el alto sale de la proporción del viewBox. -->
  <svg
    v-if="barras"
    class="codigo-barras"
    :viewBox="`0 0 ${ANCHO} ${ALTO}`"
    shape-rendering="crispEdges"
    role="img"
    :aria-label="`Código de barras ${codigo}`"
  >
    <rect
      :width="ANCHO"
      :height="ALTO"
      fill="#FFFFFF"
    />
    <rect
      v-for="barra in barras"
      :key="barra.x"
      :x="MARGEN_IZQ + barra.x"
      y="0"
      :width="barra.ancho"
      :height="barra.guarda ? ALTO_GUARDA : ALTO_BARRA"
      fill="#000000"
    />
    <g
      class="codigo-barras__digitos"
      fill="#000000"
      :font-size="TEXTO"
    >
      <text
        x="1"
        :y="ALTO - 1"
      >{{ codigo[0] }}</text>
      <text
        :x="MARGEN_IZQ + 4"
        :y="ALTO - 1"
        textLength="38"
        lengthAdjust="spacing"
      >{{ codigo.slice(1, 7) }}</text>
      <text
        :x="MARGEN_IZQ + 51"
        :y="ALTO - 1"
        textLength="38"
        lengthAdjust="spacing"
      >{{ codigo.slice(7) }}</text>
    </g>
  </svg>
  <!-- Cualquier otro código (números o letras): Code 128, lo leen todas las lectoras.
       Lo dibuja JsBarcode en este <svg>; el ancho lo pone quien lo usa. -->
  <svg
    v-else-if="esCode128"
    ref="code128Ref"
    class="codigo-barras"
    role="img"
    :aria-label="`Código de barras ${codigo}`"
  />
  <span
    v-else
    class="codigo-barras__invalido"
  >Código inválido: {{ codigo }}</span>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import JsBarcode from 'jsbarcode'
import { barrasEan13, esEan13 } from '@/utils/ean13'

const props = defineProps({
  codigo: {
    type: String,
    required: true
  }
})

// En módulos (el ancho de la barra más fina). Los márgenes en blanco a los
// costados son parte del estándar: sin ellos el lector no encuentra el inicio.
const MARGEN_IZQ = 11
const MARGEN_DER = 7
const ANCHO = MARGEN_IZQ + 95 + MARGEN_DER
const ALTO_BARRA = 46
const ALTO_GUARDA = 51
const TEXTO = 9
const ALTO = ALTO_GUARDA + TEXTO

const barras = computed(() => (esEan13(props.codigo) ? barrasEan13(props.codigo) : null))

// ── Code 128: cualquier código que no sea EAN-13 (Code 128 sólo admite ASCII) ──
const esCode128 = computed(() => !barras.value && /^[ -~]+$/.test(props.codigo ?? ''))
const code128Ref = ref()

async function dibujarCode128 () {
  await nextTick()
  if (!esCode128.value || !code128Ref.value) return
  JsBarcode(code128Ref.value, props.codigo, {
    format: 'CODE128',
    width: 1,
    height: ALTO_BARRA,
    // Zona en blanco a los costados: sin ella el lector no encuentra el inicio.
    margin: 0,
    marginLeft: 10,
    marginRight: 10,
    fontSize: TEXTO,
    font: 'Courier New',
    textMargin: 1,
    background: '#FFFFFF',
    lineColor: '#000000'
  })
  // Escalable como el EAN-13: viewBox con el tamaño que calculó JsBarcode y
  // el ancho al 100% del contenedor.
  const svg = code128Ref.value
  const ancho = svg.getAttribute('width')
  const alto = svg.getAttribute('height')
  svg.setAttribute('viewBox', `0 0 ${parseFloat(ancho)} ${parseFloat(alto)}`)
  svg.removeAttribute('width')
  svg.removeAttribute('height')
  svg.setAttribute('shape-rendering', 'crispEdges')
}

watch(() => props.codigo, dibujarCode128, { immediate: true })
</script>

<style lang="scss" scoped>
.codigo-barras {
  display: block;
  width: 100%;
  height: auto;
}

.codigo-barras__digitos {
  font-family: 'Courier New', monospace;
}

.codigo-barras__invalido {
  font-size: 9px;
  color: #000000;
}
</style>
