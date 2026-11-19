<template>
  <div
    ref="escenarioRef"
    class="en-vivo"
  >
    <div
      v-if="!sorteo"
      class="en-vivo__centro"
    >
      <q-spinner
        size="40px"
        color="white"
      />
    </div>

    <template v-else>
      <!-- ── Cabecera ── -->
      <header class="en-vivo__cabecera">
        <div class="en-vivo__marca">
          <img
            v-if="TIENDA.logo_url"
            :src="TIENDA.logo_url"
            alt=""
            class="en-vivo__logo"
          >
          <span>{{ TIENDA.nombre }}</span>
        </div>
        <div class="en-vivo__herramientas">
          <q-btn
            flat
            round
            dense
            color="white"
            :icon="pantallaCompleta ? 'fullscreen_exit' : 'fullscreen'"
            :aria-label="pantallaCompleta ? 'Salir de pantalla completa' : 'Pantalla completa'"
            @click="alternarPantallaCompleta"
          />
          <q-btn
            flat
            round
            dense
            color="white"
            :icon="conSonido ? 'volume_up' : 'volume_off'"
            :aria-label="conSonido ? 'Silenciar' : 'Activar sonido'"
            @click="conSonido = !conSonido"
          />
          <q-btn
            flat
            round
            dense
            color="white"
            icon="close"
            aria-label="Cerrar"
            @click="cerrar"
          />
        </div>
      </header>

      <div class="en-vivo__titulo">
        <h1>{{ sorteo.nombre }}</h1>
        <p>{{ totalEnJuego ?? sorteo.tickets_count }} tickets en juego · {{ sorteo.participantes_count }} participantes</p>
      </div>

      <main class="en-vivo__cuerpo">
        <!-- ── El escenario ── -->
        <section class="en-vivo__escenario">
          <template v-if="actual">
            <div class="en-vivo__premio">
              <span class="en-vivo__puesto">{{ actual.orden }}.° premio</span>
              <span class="en-vivo__premioNombre">{{ actual.descripcion }}</span>
            </div>

            <div
              :class="['en-vivo__tombola', {
                'en-vivo__tombola--girando': girando,
                'en-vivo__tombola--ganador': mostrarGanador
              }]"
              aria-live="polite"
            >
              <template v-if="pantalla">
                <div class="en-vivo__codigo">
                  {{ pantalla.codigo }}
                </div>
                <div class="en-vivo__nombre">
                  {{ pantalla.nombre }}
                </div>
                <div
                  v-if="pantalla.documento"
                  class="en-vivo__documento"
                >
                  {{ pantalla.documento }}
                </div>
              </template>
              <div
                v-else
                class="en-vivo__espera"
              >
                <q-icon
                  name="confirmation_number"
                  size="64px"
                />
                <span>¿Quién se lo lleva?</span>
              </div>
            </div>

            <div
              v-if="mostrarGanador"
              class="en-vivo__felicidades"
            >
              ¡Felicidades!
            </div>

            <div class="en-vivo__acciones">
              <template v-if="!actual.ganador">
                <q-btn
                  unelevated
                  no-caps
                  size="lg"
                  color="amber-7"
                  text-color="dark"
                  icon="casino"
                  :label="girando ? 'Sorteando…' : `Sortear el ${actual.orden}.° premio`"
                  :disable="girando || !sorteo.se_puede_sortear"
                  class="en-vivo__sortear"
                  @click="sortear"
                />
                <p
                  v-if="!sorteo.se_puede_sortear && sorteo.estado !== 'sorteado'"
                  class="en-vivo__nota"
                >
                  Se puede sortear desde el {{ fechaCorta(sorteo.termina_el) }}: hasta entonces siguen entrando tickets.
                </p>
              </template>
              <template v-else-if="!girando">
                <q-btn
                  v-if="siguiente"
                  unelevated
                  no-caps
                  size="lg"
                  color="amber-7"
                  text-color="dark"
                  icon="skip_next"
                  :label="`Siguiente: ${siguiente.orden}.° premio`"
                  @click="elegir(siguiente)"
                />
                <q-btn
                  outline
                  no-caps
                  color="white"
                  icon="person_off"
                  label="No se presentó: volver a sortear"
                  @click="descartar"
                />
              </template>
            </div>
          </template>

          <div
            v-else
            class="en-vivo__centro"
          >
            <q-icon
              name="emoji_events"
              size="72px"
            />
            <p>¡Se sortearon todos los premios! Gracias por participar.</p>
          </div>
        </section>

        <!-- ── Los premios ── -->
        <aside class="en-vivo__premios">
          <h2>Premios</h2>
          <button
            v-for="premio in ordenados"
            :key="premio.id"
            type="button"
            :class="['en-vivo__item', {
              'en-vivo__item--actual': actual?.id === premio.id,
              'en-vivo__item--ganado': premio.ganador
            }]"
            :disabled="girando"
            @click="elegir(premio)"
          >
            <span class="en-vivo__itemPuesto">{{ premio.orden }}.°</span>
            <span class="en-vivo__itemCuerpo">
              <span class="en-vivo__itemNombre">{{ premio.descripcion }}</span>
              <span
                v-if="premio.ganador"
                class="en-vivo__itemGanador"
              >
                {{ premio.ganador.publico.codigo }} · {{ premio.ganador.publico.nombre }}
              </span>
            </span>
            <q-icon
              v-if="premio.ganador"
              name="emoji_events"
              color="amber-5"
            />
          </button>
        </aside>
      </main>

      <!-- Confeti: piezas con CSS, sin librerías. -->
      <div
        v-if="confeti"
        class="en-vivo__confeti"
        aria-hidden="true"
      >
        <span
          v-for="n in 90"
          :key="n"
          :style="piezaConfeti(n)"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRouter } from 'vue-router'
import { TIENDA } from '@/config/tienda'
import SorteoService from '@/services/SorteoService'
import { fechaCorta } from './constantes'

/**
 * La pantalla que se transmite (Facebook, TikTok…). El ganador lo elige el
 * servidor al tocar "Sortear"; mientras responde, la tómbola pasa tickets
 * reales en juego y frena en el que salió. Nombre corto y documento
 * enmascarado: es una transmisión pública.
 */
const props = defineProps({
  id: {
    type: Number,
    required: true
  }
})

const $q = useQuasar()
const router = useRouter()

const sorteo = ref(null)
const actualId = ref(null)
const muestra = ref([])
const totalEnJuego = ref(null)
const pantalla = ref(null)
const girando = ref(false)
const mostrarGanador = ref(false)
const confeti = ref(false)
const conSonido = ref(true)

// De abajo hacia arriba: se deja el 1.° premio para el final.
const ordenados = computed(() => [...(sorteo.value?.premios ?? [])].sort((a, b) => a.orden - b.orden))
const pendientes = computed(() => ordenados.value.filter((p) => !p.ganador).sort((a, b) => b.orden - a.orden))
const actual = computed(() => ordenados.value.find((p) => p.id === actualId.value) ?? pendientes.value[0] ?? null)
const siguiente = computed(() => pendientes.value.find((p) => p.id !== actual.value?.id) ?? null)

async function cargar () {
  sorteo.value = await SorteoService.get(props.id)
  await refrescarCandidatos()
}

async function refrescarCandidatos () {
  try {
    const { total, data } = await SorteoService.candidatos(props.id)
    totalEnJuego.value = total
    muestra.value = data
  } catch {
    muestra.value = []
  }
}

onMounted(cargar)

function elegir (premio) {
  if (girando.value) return
  actualId.value = premio.id
  mostrarGanador.value = false
  confeti.value = false
  pantalla.value = premio.ganador ? premio.ganador.publico : null
  if (premio.ganador) mostrarGanador.value = true
}

// ── Sonido (Web Audio, sin archivos) ──
let audio = null
function tono (frecuencia, duracion, volumenInicial = 0.05) {
  if (!conSonido.value) return
  try {
    audio ??= new (window.AudioContext || window.webkitAudioContext)()
    const t = audio.currentTime
    const osc = audio.createOscillator()
    const vol = audio.createGain()
    osc.type = 'triangle'
    osc.frequency.value = frecuencia
    vol.gain.setValueAtTime(volumenInicial, t)
    vol.gain.exponentialRampToValueAtTime(0.0001, t + duracion)
    osc.connect(vol).connect(audio.destination)
    osc.start(t)
    osc.stop(t + duracion)
  } catch {
    // Sin audio: la animación alcanza.
  }
}

function fanfarria () {
  [523, 659, 784, 1047].forEach((f, i) => setTimeout(() => tono(f, 0.35, 0.08), i * 140))
}

// ── Sortear ──
const espera = (ms) => new Promise((resolve) => setTimeout(resolve, ms))
let cancelado = false

async function sortear () {
  const premio = actual.value
  if (!premio || girando.value) return
  girando.value = true
  mostrarGanador.value = false
  confeti.value = false

  // El servidor elige; mientras, la tómbola gira con tickets reales.
  const pedido = SorteoService.sortear(props.id, premio.id).then(
    (r) => ({ ok: true, r }),
    (e) => ({ ok: false, e })
  )

  const pool = muestra.value.length ? muestra.value : [{ codigo: '#??????', nombre: '…', documento: null }]
  const inicio = Date.now()
  let pausa = 45
  let resultado = null
  while (!cancelado) {
    pantalla.value = pool[Math.floor(Math.random() * pool.length)]
    tono(880, 0.04, 0.03)
    await espera(pausa)
    // Gira al menos 4 s; después frena hasta que llegue la respuesta.
    if (Date.now() - inicio > 4000) {
      resultado ??= await Promise.race([pedido, espera(0).then(() => null)])
      pausa = Math.min(pausa * 1.18, 420)
      if (resultado && pausa >= 420) break
    }
  }
  resultado ??= await pedido

  girando.value = false
  if (!resultado.ok) {
    pantalla.value = null
    const datos = resultado.e?.response?.data
    $q.notify({ type: 'negative', message: Object.values(datos?.errors ?? {})[0]?.[0] ?? datos?.message ?? 'No se pudo sortear.', position: 'top' })
    return
  }

  sorteo.value = resultado.r.sorteo
  pantalla.value = resultado.r.premio.ganador.publico
  mostrarGanador.value = true
  confeti.value = true
  fanfarria()
  setTimeout(() => { confeti.value = false }, 6000)
  refrescarCandidatos()
}

function descartar () {
  const premio = actual.value
  $q.dialog({
    title: 'Volver a sortear',
    message: `El ticket ${premio.ganador.codigo} queda descartado (en el acta) y el ${premio.orden}.° premio se sortea otra vez.`,
    prompt: { model: 'No se presentó', type: 'text', isValid: (v) => v.trim().length > 0 },
    cancel: { label: 'Volver', flat: true, noCaps: true },
    ok: { label: 'Descartar', color: 'negative', noCaps: true, unelevated: true },
    persistent: true
  }).onOk(async (motivo) => {
    const { sorteo: actualizado } = await SorteoService.descartar(props.id, premio.id, motivo.trim())
    sorteo.value = actualizado
    actualId.value = premio.id
    pantalla.value = null
    mostrarGanador.value = false
    refrescarCandidatos()
  })
}

// ── Pantalla completa ──
const escenarioRef = ref()
const pantallaCompleta = ref(false)
function alternarPantallaCompleta () {
  if (document.fullscreenElement) document.exitFullscreen()
  else escenarioRef.value?.requestFullscreen?.()
}
function alCambiarPantalla () {
  pantallaCompleta.value = Boolean(document.fullscreenElement)
}
document.addEventListener('fullscreenchange', alCambiarPantalla)
onBeforeUnmount(() => {
  cancelado = true
  document.removeEventListener('fullscreenchange', alCambiarPantalla)
})

function cerrar () {
  if (window.opener) window.close()
  else router.push(`/sorteos/${props.id}`)
}

// Confeti: posición, color, retraso y giro distintos por pieza.
const COLORES = ['#FFC107', '#FF5252', '#40C4FF', '#69F0AE', '#E040FB', '#FFFFFF']
function piezaConfeti (n) {
  const azar = (semilla) => ((Math.sin(n * semilla) + 1) / 2)
  return {
    left: `${azar(12.9898) * 100}%`,
    background: COLORES[n % COLORES.length],
    animationDelay: `${azar(78.233) * 1.2}s`,
    animationDuration: `${2.6 + azar(37.719) * 2.4}s`,
    transform: `rotate(${azar(4.1) * 360}deg)`
  }
}
</script>

<style lang="scss" scoped>
// Escenario para transmitir: oscuro y de alto contraste a propósito, igual
// en tema claro u oscuro (es lo que ve el público).
.en-vivo {
  position: relative;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  padding: 20px 28px 28px;
  overflow: hidden;
  background: radial-gradient(ellipse at top, #1E3A8A 0%, #0B1023 60%, #05070F 100%);
  color: #FFFFFF;
}

.en-vivo__centro {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  font-size: 20px;
  text-align: center;
}

.en-vivo__cabecera {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.en-vivo__marca {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
  font-weight: 700;
  letter-spacing: 0.4px;
  opacity: 0.9;
}

.en-vivo__logo {
  height: 36px;
  border-radius: 6px;
  background: #FFFFFF;
  padding: 2px;
}

.en-vivo__titulo {
  margin: 8px 0 18px;
  text-align: center;

  h1 {
    margin: 0;
    font-size: clamp(28px, 4.5vw, 54px);
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.5px;
  }

  p {
    margin: 6px 0 0;
    font-size: 16px;
    opacity: 0.75;
  }
}

.en-vivo__cuerpo {
  display: grid;
  flex: 1;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 24px;
}

.en-vivo__escenario {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 22px;
  text-align: center;
}

.en-vivo__premio {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.en-vivo__puesto {
  font-size: 18px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 3px;
  color: #FFC107;
}

.en-vivo__premioNombre {
  font-size: clamp(22px, 3vw, 36px);
  font-weight: 700;
}

.en-vivo__tombola {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: min(640px, 100%);
  min-height: 260px;
  padding: 28px;
  border: 2px solid rgba(255, 255, 255, 0.18);
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.06);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
  transition: border-color 0.3s ease, box-shadow 0.3s ease, transform 0.3s ease;

  &--girando {
    border-color: rgba(255, 193, 7, 0.5);
  }

  &--ganador {
    border-color: #FFC107;
    box-shadow: 0 0 0 6px rgba(255, 193, 7, 0.25), 0 20px 80px rgba(255, 193, 7, 0.35);
    transform: scale(1.04);
  }
}

.en-vivo__codigo {
  font-size: clamp(48px, 8vw, 104px);
  font-weight: 800;
  line-height: 1;
  letter-spacing: 2px;
  font-variant-numeric: tabular-nums;
  color: #FFC107;
}

.en-vivo__nombre {
  font-size: clamp(26px, 4vw, 48px);
  font-weight: 700;
}

.en-vivo__documento {
  font-size: 18px;
  opacity: 0.7;
  font-variant-numeric: tabular-nums;
}

.en-vivo__espera {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  font-size: 24px;
  opacity: 0.7;
}

.en-vivo__felicidades {
  font-size: clamp(28px, 4vw, 44px);
  font-weight: 800;
  color: #FFC107;
  animation: latido 1s ease-in-out infinite;
}

.en-vivo__acciones {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.en-vivo__sortear {
  padding: 6px 24px;
  border-radius: 14px;
  font-weight: 800;
}

.en-vivo__nota {
  flex-basis: 100%;
  margin: 0;
  font-size: 14px;
  opacity: 0.75;
}

.en-vivo__premios {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.05);

  h2 {
    margin: 0 0 4px;
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2px;
    opacity: 0.7;
  }
}

.en-vivo__item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 12px;
  border: 1px solid transparent;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.04);
  color: inherit;
  text-align: left;
  cursor: pointer;

  &:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.1);
  }

  &--actual {
    border-color: #FFC107;
  }

  &--ganado {
    opacity: 0.85;
  }
}

.en-vivo__itemPuesto {
  min-width: 30px;
  font-weight: 800;
  color: #FFC107;
}

.en-vivo__itemCuerpo {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.en-vivo__itemNombre {
  font-weight: 600;
}

.en-vivo__itemGanador {
  font-size: 12px;
  opacity: 0.75;
}

.en-vivo__confeti {
  position: absolute;
  inset: 0;
  pointer-events: none;

  span {
    position: absolute;
    top: -20px;
    width: 10px;
    height: 16px;
    border-radius: 2px;
    animation-name: caer;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
  }
}

@keyframes caer {
  to {
    top: 105%;
    transform: rotate(720deg) translateX(40px);
  }
}

@keyframes latido {
  50% {
    transform: scale(1.08);
  }
}

@media (max-width: 900px) {
  .en-vivo {
    padding: 16px;
  }

  .en-vivo__cuerpo {
    grid-template-columns: 1fr;
  }
}

@media (prefers-reduced-motion: reduce) {
  .en-vivo__felicidades,
  .en-vivo__confeti span {
    animation: none;
  }
}
</style>
