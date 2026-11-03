<template>
  <div
    ref="contenedorRef"
    class="grafico"
  >
    <svg
      v-if="ancho"
      :width="ancho"
      :height="ALTO"
      role="img"
      :aria-label="`Ventas de los últimos ${dias.length} días`"
      class="grafico__svg"
      @mouseleave="activo = null"
    >
      <!-- Grilla recesiva: 3 referencias y la base. -->
      <g class="grafico__grilla">
        <template
          v-for="t in ticks"
          :key="t"
        >
          <line
            :x1="MARGEN.izq"
            :x2="ancho - MARGEN.der"
            :y1="y(t)"
            :y2="y(t)"
          />
          <text
            :x="MARGEN.izq - 8"
            :y="y(t)"
            dy="0.32em"
            text-anchor="end"
          >{{ etiquetaEje(t) }}</text>
        </template>
      </g>

      <g
        v-for="(d, i) in dias"
        :key="d.fecha"
      >
        <!-- Barra: esquinas superiores redondeadas, anclada a la base. -->
        <path
          v-if="d.total > 0"
          :d="barra(i, d.total)"
          :class="['grafico__barra', { 'grafico__barra--activa': activo === i, 'grafico__barra--hoy': i === dias.length - 1 }]"
        />
        <text
          v-if="mostrarEtiqueta(i)"
          :x="xCentro(i)"
          :y="ALTO - 6"
          text-anchor="middle"
          :class="['grafico__x', { 'grafico__x--hoy': i === dias.length - 1 }]"
        >{{ i === dias.length - 1 ? 'Hoy' : diaCorto(d.fecha) }}</text>
        <!-- Zona de hover: toda la columna, más grande que la barra. -->
        <rect
          :x="MARGEN.izq + i * paso"
          :y="MARGEN.arr"
          :width="paso"
          :height="altoPlot"
          class="grafico__hit"
          @mouseenter="activo = i"
          @focus="activo = i"
        />
      </g>
    </svg>

    <div
      v-if="activo !== null && dias[activo]"
      class="grafico__tooltip"
      :style="estiloTooltip"
      role="status"
    >
      <div class="grafico__tooltipFecha">
        {{ fechaLarga(dias[activo].fecha) }}
      </div>
      <div class="grafico__tooltipValor text-mono">
        {{ formatearPrecio(dias[activo].total) }}
      </div>
      <div class="grafico__tooltipDetalle">
        {{ dias[activo].ventas }} {{ dias[activo].ventas === 1 ? 'venta' : 'ventas' }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { formatearPrecio } from '@/utils/moneda'

/**
 * Barras de ventas por día (una serie: sin leyenda, el título la nombra).
 * SVG a mano y no una librería: es un solo gráfico. Ancho medido con
 * ResizeObserver para que el texto y las esquinas no se deformen.
 */
const props = defineProps({
  // [{ fecha: 'YYYY-MM-DD', ventas, total }], del más viejo a hoy.
  dias: {
    type: Array,
    required: true
  }
})

const ALTO = 220
const MARGEN = { arr: 12, der: 8, aba: 26, izq: 56 }
const RADIO = 4
const SEPARACION = 2

const contenedorRef = ref()
const ancho = ref(0)
const activo = ref(null)

let observador
onMounted(() => {
  observador = new ResizeObserver(([entrada]) => { ancho.value = Math.floor(entrada.contentRect.width) })
  observador.observe(contenedorRef.value)
})
onBeforeUnmount(() => observador?.disconnect())

const altoPlot = ALTO - MARGEN.arr - MARGEN.aba
const paso = computed(() => (ancho.value - MARGEN.izq - MARGEN.der) / Math.max(props.dias.length, 1))
// Barras finas: hasta 60% de la columna y nunca más de 28px.
const anchoBarra = computed(() => Math.max(4, Math.min(28, paso.value * 0.6) - SEPARACION))

// Máximo "redondo" para que las referencias sean números legibles.
const maximo = computed(() => {
  const mayor = Math.max(...props.dias.map((d) => d.total), 0)
  if (mayor <= 0) return 100
  const magnitud = 10 ** Math.floor(Math.log10(mayor))
  return Math.ceil(mayor / magnitud) * magnitud
})

const ticks = computed(() => [0, maximo.value / 2, maximo.value])

function y (valor) {
  return MARGEN.arr + altoPlot - (valor / maximo.value) * altoPlot
}

function xCentro (i) {
  return MARGEN.izq + i * paso.value + paso.value / 2
}

function barra (i, valor) {
  const w = anchoBarra.value
  const x = xCentro(i) - w / 2
  const base = y(0)
  const tope = Math.min(y(valor), base - 2)
  const r = Math.min(RADIO, w / 2, base - tope)
  return `M${x},${base} V${tope + r} Q${x},${tope} ${x + r},${tope} H${x + w - r} Q${x + w},${tope} ${x + w},${tope + r} V${base} Z`
}

// Con columnas angostas, una etiqueta sí y otra no (siempre "Hoy").
function mostrarEtiqueta (i) {
  if (i === props.dias.length - 1) return true
  return paso.value >= 36 || (props.dias.length - 1 - i) % 2 === 0
}

function etiquetaEje (valor) {
  if (valor >= 1000) return `S/ ${(valor / 1000).toLocaleString('es-PE', { maximumFractionDigits: 1 })}k`
  return `S/ ${Math.round(valor)}`
}

const formatoDia = new Intl.DateTimeFormat('es-PE', { day: 'numeric', timeZone: 'UTC' })
const formatoLargo = new Intl.DateTimeFormat('es-PE', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' })

function diaCorto (iso) {
  return formatoDia.format(new Date(`${iso}T00:00:00Z`))
}

function fechaLarga (iso) {
  const texto = formatoLargo.format(new Date(`${iso}T00:00:00Z`))
  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

// El tooltip sigue a la columna y no se sale por los costados.
const estiloTooltip = computed(() => {
  const x = xCentro(activo.value)
  const izquierda = Math.min(Math.max(x - 80, 0), ancho.value - 160)
  return { left: `${izquierda}px`, top: `${Math.max(0, y(props.dias[activo.value].total) - 78)}px` }
})
</script>

<style lang="scss" scoped>
.grafico {
  // Ámbar oscuro y no el amarillo de marca: #f3c01e sobre blanco da 1.65:1
  // (validado con el validador de paletas; éste pasa en claro y oscuro).
  --grafico-barra: #B7860B;

  position: relative;
  width: 100%;
  min-height: 220px;
}

:global(body.body--dark) .grafico {
  --grafico-barra: #B8890D;
}

.grafico__svg {
  display: block;
  overflow: visible;
}

.grafico__grilla {
  line {
    stroke: var(--app-border-subtle);
    stroke-width: 1;
  }

  text {
    font-size: 11px;
    fill: var(--app-ink-2);
  }
}

.grafico__barra {
  fill: var(--grafico-barra);
  opacity: 0.85;
  transition: opacity 0.12s ease;

  &--hoy,
  &--activa {
    opacity: 1;
  }
}

.grafico__x {
  font-size: 11px;
  fill: var(--app-ink-2);

  &--hoy {
    font-weight: 700;
    fill: var(--app-ink);
  }
}

.grafico__hit {
  fill: transparent;
  cursor: default;
}

.grafico__tooltip {
  position: absolute;
  z-index: 1;
  width: 160px;
  padding: 8px 10px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 8px;
  background: var(--app-surface);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
  pointer-events: none;
}

.grafico__tooltipFecha {
  font-size: 11px;
  color: var(--app-ink-2);
}

.grafico__tooltipValor {
  font-size: 15px;
  font-weight: 700;
  color: var(--app-ink);
}

.grafico__tooltipDetalle {
  font-size: 12px;
  color: var(--app-ink-2);
}
</style>
