<template>
  <div class="app-firma">
    <canvas
      ref="lienzo"
      class="app-firma__lienzo"
      aria-label="Recuadro para firmar"
      @pointerdown="empezar"
      @pointermove="trazar"
      @pointerup="terminar"
      @pointerleave="terminar"
      @pointercancel="terminar"
    />
    <div class="app-firma__pie">
      <span class="app-firma__ayuda">{{ vacia ? 'Firme dentro del recuadro con el dedo o el mouse' : 'Firma lista' }}</span>
      <button
        type="button"
        class="app-firma__borrar"
        :disabled="vacia"
        @click="borrar"
      >
        Borrar
      </button>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Recuadro para que el cliente firme en la pantalla (dedo, lápiz o mouse).
 * `imagen()` devuelve la firma como PNG en data URL, o null si está vacía.
 * No es una firma electrónica certificada: es la conformidad del cliente.
 */
const lienzo = ref()
const vacia = ref(true)
let ctx = null
let dibujando = false

// El lienzo se dibuja a la resolución real de la pantalla (nítido en
// celulares). OJO: se mide con offsetWidth/offsetHeight (el tamaño de
// diseño) y no con getBoundingClientRect: dentro de un diálogo que se está
// abriendo, la animación lo achica (o mide 0) y el lienzo quedaba de 0×0:
// no se veía el trazo y se enviaba una imagen vacía.
function ajustar () {
  const canvas = lienzo.value
  const escala = window.devicePixelRatio || 1
  const ancho = canvas.offsetWidth
  const alto = canvas.offsetHeight
  if (!ancho || !alto) return false
  canvas.width = Math.round(ancho * escala)
  canvas.height = Math.round(alto * escala)
  ctx = canvas.getContext('2d')
  ctx.scale(escala, escala)
  ctx.lineWidth = 2.4
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.strokeStyle = '#111827'
  vacia.value = true
  return true
}

// ¿El lienzo ya tiene el tamaño de su caja? (no lo tiene si se preparó
// mientras el diálogo estaba oculto o animándose).
function listo () {
  const canvas = lienzo.value
  const escala = window.devicePixelRatio || 1
  return ctx !== null && canvas.width === Math.round(canvas.offsetWidth * escala) && canvas.width > 0
}

// Coordenadas en el tamaño de diseño, aunque el diálogo esté escalado.
function punto (evento) {
  const canvas = lienzo.value
  const caja = canvas.getBoundingClientRect()
  const factor = caja.width ? canvas.offsetWidth / caja.width : 1
  return { x: (evento.clientX - caja.left) * factor, y: (evento.clientY - caja.top) * factor }
}

function empezar (evento) {
  evento.preventDefault()
  // Recién acá se sabe seguro el tamaño real: si hace falta, se prepara ahora.
  if (vacia.value && !listo() && !ajustar()) return
  lienzo.value.setPointerCapture?.(evento.pointerId)
  dibujando = true
  const { x, y } = punto(evento)
  ctx.beginPath()
  ctx.moveTo(x, y)
  // Un toque solo también deja marca.
  ctx.lineTo(x + 0.1, y + 0.1)
  ctx.stroke()
  vacia.value = false
}

function trazar (evento) {
  if (!dibujando) return
  evento.preventDefault()
  const { x, y } = punto(evento)
  ctx.lineTo(x, y)
  ctx.stroke()
}

function terminar () {
  dibujando = false
}

function borrar () {
  ctx?.clearRect(0, 0, lienzo.value.width, lienzo.value.height)
  vacia.value = true
}

// Sin trazo (o con un lienzo sin tamaño) no hay firma: null, nunca "data:,".
function imagen () {
  if (vacia.value || !lienzo.value.width) return null
  const datos = lienzo.value.toDataURL('image/png')
  return datos.startsWith('data:image/png;base64,') ? datos : null
}

let observador
onMounted(() => {
  ajustar()
  observador = new ResizeObserver(() => { if (vacia.value) ajustar() })
  observador.observe(lienzo.value)
})
onBeforeUnmount(() => observador?.disconnect())

defineExpose({ imagen, borrar, vacia })
</script>

<style lang="scss" scoped>
.app-firma {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

// Siempre blanco (también en tema oscuro): la firma es tinta negra.
.app-firma__lienzo {
  width: 100%;
  height: 180px;
  border: 1.5px dashed var(--app-border-control-hover);
  border-radius: 10px;
  background: #FFFFFF;
  cursor: crosshair;
  // Sin esto el dedo hace scroll en vez de firmar.
  touch-action: none;
}

.app-firma__pie {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.app-firma__ayuda {
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.app-firma__borrar {
  padding: 4px 10px;
  border: 1px solid var(--app-border-control);
  border-radius: 8px;
  background: var(--app-surface);
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink);
  cursor: pointer;

  &:disabled {
    opacity: 0.45;
    cursor: default;
  }
}
</style>
