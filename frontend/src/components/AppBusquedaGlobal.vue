<template>
  <div
    ref="raizRef"
    class="busqueda"
  >
    <q-icon
      name="search"
      class="busqueda__icono"
    />
    <input
      ref="inputRef"
      v-model="texto"
      type="search"
      class="busqueda__campo"
      placeholder="Buscar pedidos, clientes, productos…"
      aria-label="Búsqueda global"
      autocomplete="off"
      @focus="abierto = texto.trim().length >= 2"
      @keydown.down.prevent="mover(1)"
      @keydown.up.prevent="mover(-1)"
      @keydown.enter.prevent="ir(planos[activo])"
      @keydown.esc="cerrar"
    >
    <kbd
      v-if="!texto && !pantallaCompleta"
      class="busqueda__atajo"
    >Ctrl K</kbd>

    <q-menu
      v-model="abierto"
      :target="raizRef"
      no-focus
      no-refocus
      no-parent-event
      fit
      anchor="bottom left"
      self="top left"
      :offset="[0, 6]"
    >
      <div class="busqueda__panel">
        <div
          v-if="cargando && !planos.length"
          class="busqueda__estado"
        >
          <q-spinner size="20px" />
        </div>
        <div
          v-else-if="!planos.length"
          class="busqueda__estado"
        >
          Nada coincide con “{{ texto.trim() }}”.
        </div>

        <template
          v-for="grupo in grupos"
          :key="grupo.clave"
        >
          <div class="busqueda__grupo">
            <q-icon
              :name="grupo.icono"
              size="15px"
            />
            {{ grupo.label }}
          </div>
          <button
            v-for="r in grupo.items"
            :key="`${grupo.clave}-${r.id}`"
            type="button"
            :class="['busqueda__item', { 'busqueda__item--activo': planos[activo] === r }]"
            @mouseenter="activo = planos.indexOf(r)"
            @click="ir(r)"
          >
            <img
              v-if="r.imagen"
              :src="r.imagen"
              alt=""
              class="busqueda__img"
            >
            <span class="busqueda__texto">
              <span class="busqueda__titulo">{{ r.titulo }}</span>
              <span
                v-if="r.detalle"
                class="busqueda__detalle"
              >{{ r.detalle }}</span>
            </span>
          </button>
        </template>
      </div>
    </q-menu>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PanelService from '@/services/PanelService'

/**
 * Búsqueda global del toolbar: pedidos, clientes, productos y cotizaciones a
 * la vez (cada grupo sólo si el usuario puede ver esa pantalla). Ctrl+K la
 * enfoca, salvo en el punto de venta, donde Ctrl+K es su propio buscador.
 */
const GRUPOS = [
  { clave: 'pedidos', label: 'Pedidos', icono: 'receipt_long' },
  { clave: 'clientes', label: 'Clientes', icono: 'groups' },
  { clave: 'productos', label: 'Productos', icono: 'inventory_2' },
  { clave: 'cotizaciones', label: 'Cotizaciones', icono: 'request_quote' }
]

const route = useRoute()
const router = useRouter()

const raizRef = ref()
const inputRef = ref()
const texto = ref('')
const abierto = ref(false)
const cargando = ref(false)
const resultados = ref({})
const activo = ref(0)

const pantallaCompleta = computed(() => Boolean(route.meta.pantallaCompleta))

const grupos = computed(() => GRUPOS
  .map((g) => ({ ...g, items: resultados.value[g.clave] ?? [] }))
  .filter((g) => g.items.length))

// En el orden en que se ven: para moverse con las flechas.
const planos = computed(() => grupos.value.flatMap((g) => g.items))

// Sólo vale la respuesta de la última consulta (se tipea rápido).
let ultima = 0
let timer
watch(texto, (valor) => {
  clearTimeout(timer)
  const q = valor.trim()
  if (q.length < 2) {
    abierto.value = false
    resultados.value = {}
    return
  }
  timer = setTimeout(() => buscar(q), 250)
})

async function buscar (q) {
  const consulta = ++ultima
  cargando.value = true
  abierto.value = true
  try {
    const data = await PanelService.buscar(q)
    if (consulta !== ultima) return
    resultados.value = data
    activo.value = 0
  } finally {
    if (consulta === ultima) cargando.value = false
  }
}

function mover (delta) {
  if (!planos.value.length) return
  activo.value = (activo.value + delta + planos.value.length) % planos.value.length
}

function ir (resultado) {
  if (!resultado) return
  router.push(resultado.to)
  cerrar()
  texto.value = ''
  inputRef.value?.blur()
}

function cerrar () {
  abierto.value = false
}

// Ctrl+K / ⌘K enfoca el buscador (no en el POS: ahí es el suyo).
function atajo (evento) {
  if (pantallaCompleta.value) return
  if ((evento.ctrlKey || evento.metaKey) && evento.key.toLowerCase() === 'k') {
    evento.preventDefault()
    inputRef.value?.focus()
    inputRef.value?.select()
  }
}

onMounted(() => window.addEventListener('keydown', atajo))
onBeforeUnmount(() => window.removeEventListener('keydown', atajo))
</script>

<style lang="scss" scoped>
.busqueda {
  display: flex;
  align-items: center;
  gap: 9px;
  width: 360px;
  max-width: 100%;
  height: 40px;
  padding: 0 10px 0 13px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 9px;
  background: var(--app-page);

  &:focus-within {
    border-color: var(--app-border-control-hover);
  }
}

.busqueda__icono {
  flex-shrink: 0;
  font-size: 17px;
  color: var(--app-ink-2);
}

.busqueda__campo {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  font: inherit;
  font-size: 13.5px;
  color: var(--app-ink);

  &::placeholder {
    color: var(--app-ink-2);
  }

  &::-webkit-search-cancel-button {
    display: none;
  }
}

.busqueda__atajo {
  flex-shrink: 0;
  padding: 1px 6px;
  border: 1px solid var(--app-border-control);
  border-radius: 5px;
  font-family: inherit;
  font-size: 11px;
  color: var(--app-ink-2);
}

.busqueda__panel {
  max-height: 60vh;
  overflow-y: auto;
  padding: 6px 0;
  background: var(--app-surface);
  color: var(--app-ink);
}

.busqueda__estado {
  display: flex;
  justify-content: center;
  padding: 18px 16px;
  font-size: 13px;
  color: var(--app-ink-2);
}

.busqueda__grupo {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 14px 4px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--app-ink-2);
}

.busqueda__item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 14px;
  border: 0;
  background: none;
  text-align: left;
  color: inherit;
  cursor: pointer;

  &--activo {
    background: var(--app-brand-soft);
  }
}

.busqueda__img {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  object-fit: cover;
  flex-shrink: 0;
}

.busqueda__texto {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.busqueda__titulo {
  font-size: 14px;
  font-weight: 600;
}

.busqueda__detalle {
  overflow: hidden;
  font-size: 12px;
  white-space: nowrap;
  text-overflow: ellipsis;
  color: var(--app-ink-2);
}
</style>
