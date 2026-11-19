<template>
  <q-dialog
    v-model="abierta"
    persistent
    @show="encender"
    @hide="apagar"
  >
    <div
      class="app-camara"
      role="dialog"
      aria-label="Tomar foto con la cámara"
    >
      <div class="app-camara__visor">
        <video
          ref="videoRef"
          class="app-camara__video"
          :class="{ 'app-camara__video--espejo': frontal }"
          autoplay
          playsinline
          muted
        />
        <div
          v-if="flash"
          class="app-camara__flash"
        />
        <div
          v-if="cargando"
          class="app-camara__estado"
          role="status"
        >
          <q-spinner size="28px" />
          <span>Encendiendo la cámara…</span>
        </div>
        <div
          v-else-if="error"
          class="app-camara__estado"
          role="alert"
        >
          <q-icon
            name="no_photography"
            size="32px"
          />
          <span>{{ error }}</span>
        </div>
        <span
          v-if="tomadas"
          class="app-camara__contador"
        >{{ tomadas }} {{ tomadas === 1 ? 'foto' : 'fotos' }}</span>
      </div>

      <div class="app-camara__barra">
        <q-btn
          flat
          no-caps
          label="Listo"
          color="white"
          @click="abierta = false"
        />
        <button
          type="button"
          class="app-camara__disparo"
          :disabled="!lista || restantes <= tomadas"
          aria-label="Tomar foto"
          @click="disparar"
        />
        <q-btn
          v-if="camaras.length > 1"
          flat
          round
          icon="cameraswitch"
          color="white"
          aria-label="Cambiar de cámara"
          @click="cambiar"
        />
        <span
          v-else
          class="app-camara__hueco"
        />
      </div>
      <p
        v-if="lista && restantes <= tomadas"
        class="app-camara__tope"
      >
        Llegaste al máximo de fotos.
      </p>
    </div>
  </q-dialog>
</template>

<script setup>
import { computed, ref } from 'vue'

/**
 * La cámara en vivo (webcam de la PC o cámara del celular) para tomar fotos
 * sin salir del sistema. Cada disparo emite un File JPG (`foto`); el que la
 * usa lo procesa como cualquier foto elegida. Se puede disparar varias veces
 * hasta `restantes`. Necesita HTTPS o localhost (getUserMedia).
 */
const props = defineProps({
  // Cuántas fotos más entran en la galería.
  restantes: {
    type: Number,
    default: 6
  }
})
const emit = defineEmits(['foto'])
const abierta = defineModel({ type: Boolean, default: false })

const videoRef = ref()
const cargando = ref(false)
const error = ref('')
const flash = ref(false)
const tomadas = ref(0)
const camaras = ref([])
// Atrás primero (la del celular que apunta al equipo); en una PC hay una sola.
const modo = ref('environment')
const frontal = computed(() => modo.value === 'user')
const lista = computed(() => !cargando.value && !error.value)
let stream = null

async function encender () {
  tomadas.value = 0
  error.value = ''
  cargando.value = true
  detener()
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: modo.value }, width: { ideal: 1920 }, height: { ideal: 1080 } },
      audio: false
    })
    videoRef.value.srcObject = stream
    await videoRef.value.play()
    const dispositivos = await navigator.mediaDevices.enumerateDevices()
    camaras.value = dispositivos.filter((d) => d.kind === 'videoinput')
  } catch (e) {
    error.value = mensajeDe(e)
  } finally {
    cargando.value = false
  }
}

function mensajeDe (e) {
  if (e?.name === 'NotAllowedError') return 'No diste permiso a la cámara. Actívalo en el candado de la barra de direcciones y vuelve a intentar.'
  if (e?.name === 'NotFoundError' || e?.name === 'OverconstrainedError') return 'No se encontró ninguna cámara en este equipo.'
  if (e?.name === 'NotReadableError') return 'La cámara está ocupada por otro programa (Zoom, Meet…). Ciérralo y vuelve a intentar.'
  return 'No se pudo abrir la cámara.'
}

function detener () {
  stream?.getTracks().forEach((t) => t.stop())
  stream = null
}

function apagar () {
  detener()
  if (videoRef.value) videoRef.value.srcObject = null
}

function cambiar () {
  modo.value = frontal.value ? 'environment' : 'user'
  encender()
}

function disparar () {
  const video = videoRef.value
  if (!video?.videoWidth) return

  const canvas = document.createElement('canvas')
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  const ctx = canvas.getContext('2d')
  // La frontal se ve en espejo; la foto sale derecha (como la ve el otro).
  ctx.drawImage(video, 0, 0)

  flash.value = true
  setTimeout(() => { flash.value = false }, 120)

  canvas.toBlob((blob) => {
    if (!blob) return
    tomadas.value++
    const hora = new Date().toTimeString().slice(0, 8).replaceAll(':', '')
    emit('foto', new File([blob], `camara-${hora}-${tomadas.value}.jpg`, { type: 'image/jpeg', lastModified: Date.now() }))
  }, 'image/jpeg', 0.92)
}
</script>

<style lang="scss" scoped>
.app-camara {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: min(720px, 94vw);
  padding: 12px;
  border-radius: 16px;
  background: #0B0F17;
  color: #FFFFFF;
}

.app-camara__visor {
  position: relative;
  aspect-ratio: 4 / 3;
  overflow: hidden;
  border-radius: 12px;
  background: #000000;
}

.app-camara__video {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.app-camara__video--espejo {
  transform: scaleX(-1);
}

.app-camara__flash {
  position: absolute;
  inset: 0;
  background: #FFFFFF;
  opacity: 0.7;
}

.app-camara__estado {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 24px;
  font-size: 14px;
  text-align: center;
}

.app-camara__contador {
  position: absolute;
  top: 10px;
  right: 10px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  background: rgba(0, 0, 0, 0.6);
}

.app-camara__barra {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  justify-items: center;
}

.app-camara__hueco {
  width: 42px;
}

// Botón de disparo como el de una cámara: aro blanco y centro.
.app-camara__disparo {
  width: 64px;
  height: 64px;
  padding: 0;
  border: 4px solid #FFFFFF;
  border-radius: 50%;
  background: #FFFFFF;
  box-shadow: inset 0 0 0 3px #0B0F17;
  cursor: pointer;
  transition: transform 0.1s;

  &:active:not(:disabled) {
    transform: scale(0.92);
  }

  &:disabled {
    opacity: 0.35;
    cursor: not-allowed;
  }

  &:focus-visible {
    outline: 3px solid var(--q-primary);
    outline-offset: 3px;
  }
}

.app-camara__tope {
  margin: 0;
  font-size: 12px;
  text-align: center;
  color: #FBBF24;
}
</style>
