<template>
  <div class="catalogo">
    <div
      v-if="productos.length"
      :class="['catalogo__lista', `catalogo__lista--${vista}`]"
    >
      <div
        v-for="p in productos"
        :key="p.id"
        :class="['tarjeta-envoltura', `tarjeta-envoltura--${vista}`]"
      >
        <button
          type="button"
          :class="['tarjeta', `tarjeta--${vista}`, { 'tarjeta--agotada': !p.stock_total }]"
          :disabled="!p.stock_total"
          :aria-label="`${p.nombre}, ${rangoPrecio(p)}, stock ${p.stock_total}`"
          @click="emit('elegir', p)"
        >
          <div
            class="tarjeta__img"
            :style="fondo(p)"
          >
            <q-icon
              v-if="!p.miniatura_url"
              name="checkroom"
              size="28px"
              class="tarjeta__ph"
            />
            <span :class="['tarjeta__stock', `tarjeta__stock--${nivelStock(p.stock_total)}`]">
              <span class="tarjeta__punto" />
              {{ p.stock_total ? `STOCK · ${p.stock_total}` : 'AGOTADO' }}
            </span>
            <span
              v-if="p.vendidos > 0 && vista === 'grid'"
              class="tarjeta__vendidos"
            >
              <q-icon
                name="trending_up"
                size="11px"
              />{{ p.vendidos }} vendidos
            </span>
            <span
              v-if="pos.cantidadDeProducto(p.id)"
              class="tarjeta__enCarrito"
            >×{{ pos.cantidadDeProducto(p.id) }}</span>
            <span
              v-if="p.oferta"
              class="tarjeta__oferta"
              :title="`${p.oferta.nombre} · hasta ${formatearFechaHora(p.oferta.termina_at)}`"
            >{{ p.oferta.etiqueta }}</span>
          </div>

          <div class="tarjeta__cuerpo">
            <div class="tarjeta__nombre">
              {{ p.nombre }}
            </div>
            <div class="tarjeta__categoria">
              <template v-if="p.marca">
                {{ p.marca.nombre }} ·
              </template>{{ p.categoria?.nombre }}
            </div>

            <div class="tarjeta__variantes">
              <span
                v-for="t in presentacionesDisponibles(p)"
                :key="t"
                class="tarjeta__presentacion"
              >{{ t }}</span>
              <span
                v-for="c in coloresDisponibles(p)"
                :key="c.nombre"
                class="tarjeta__color"
                :style="{ background: c.hexadecimal }"
                :title="c.nombre"
              />
            </div>

            <div class="tarjeta__pie">
              <span class="tarjeta__precios">
                <s
                  v-if="enOferta(p)"
                  class="tarjeta__lista text-mono"
                >{{ rangoPrecio(p, 'precio_lista') }}</s>
                <span :class="['tarjeta__precio', 'text-mono', { 'tarjeta__precio--oferta': enOferta(p) }]">{{ rangoPrecio(p) }}</span>
              </span>
              <span class="tarjeta__agregar">
                <q-icon
                  name="add"
                  size="16px"
                />
              </span>
            </div>
          </div>
        </button>
        <!-- Hermano y no hijo de la tarjeta (un botón no va dentro de otro).
             Funciona también con la tarjeta agotada: ahí es cuando más sirve
             ver si hay en otra sede. -->
        <button
          type="button"
          class="tarjeta__info"
          :aria-label="`Ver ficha de ${p.nombre}`"
          @click="fichaRef.abrir(p)"
        >
          <q-icon
            name="info_outline"
            size="16px"
          />
          <q-tooltip>Ver ficha</q-tooltip>
        </button>
      </div>
    </div>

    <div
      v-else-if="!cargando"
      class="catalogo__vacio"
    >
      <q-icon
        name="search_off"
        size="44px"
      />
      <div class="catalogo__vacioTitulo">
        Sin resultados
      </div>
      <div>Probá con otro término o ajustá los filtros.</div>
    </div>

    <div
      v-if="cargando"
      class="catalogo__cargando"
    >
      <q-spinner size="28px" />
    </div>

    <div
      v-if="!cargando && hayMas"
      class="catalogo__mas"
    >
      <AppButton
        variant="tertiary"
        :label="`Cargar más (${productos.length} de ${total})`"
        @click="cargar(pagina + 1)"
      />
    </div>

    <FichaProductoPos
      ref="fichaRef"
      @elegir="(p) => emit('elegir', p)"
    />
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import AppButton from '@/components/AppButton.vue'
import VentaService from '@/services/VentaService'
import { usePosStore } from '@/stores/pos-store'
import FichaProductoPos from './FichaProductoPos.vue'
import { formatearFechaHora } from '@/utils/fechas'
import { formatearPrecio } from '@/utils/moneda'

const props = defineProps({
  // { categoria_id, marca_id, color_id, con_stock }
  filtros: {
    type: Object,
    required: true
  },
  search: {
    type: String,
    default: ''
  },
  orden: {
    type: String,
    default: 'vendidos'
  },
  vista: {
    type: String,
    default: 'grid'
  }
})

const emit = defineEmits(['elegir'])

const pos = usePosStore()
const fichaRef = ref()

const POR_PAGINA = 24
const productos = ref([])
const total = ref(0)
const pagina = ref(1)
const cargando = ref(false)
const hayMas = computed(() => productos.value.length < total.value)

// Sólo vale la respuesta de la última consulta (filtros que cambian rápido).
let ultimaConsulta = 0

async function cargar (numero = 1) {
  const consulta = ++ultimaConsulta
  cargando.value = true
  try {
    const { categoria_id: categoriaId, marca_id: marcaId, color_id: colorId, con_stock: conStock } = props.filtros
    const params = {
      page: numero,
      rowsPerPage: POR_PAGINA,
      order_by: props.orden,
      ...(props.search && { search: props.search }),
      ...(categoriaId && { categoria_id: categoriaId }),
      ...(marcaId && { marca_id: marcaId }),
      ...(colorId && { color_id: colorId }),
      ...(conStock && { con_stock: 1 })
    }
    const respuesta = await VentaService.catalogo({ params })
    if (consulta !== ultimaConsulta) return

    productos.value = numero === 1 ? respuesta.data : [...productos.value, ...respuesta.data]
    total.value = respuesta.total
    pagina.value = numero
  } finally {
    if (consulta === ultimaConsulta) cargando.value = false
  }
}

watch(() => [props.filtros, props.search, props.orden], () => cargar(1), { deep: true, immediate: true })

// ── Presentación ──
function enOferta (p) {
  return p.variantes.some((v) => Number(v.precio) < Number(v.precio_lista))
}

function nivelStock (stock) {
  if (!stock) return 'agotado'
  if (stock <= 5) return 'bajo'
  return stock <= 20 ? 'medio' : 'alto'
}

// Sin foto: un degradé suave con un tono derivado del nombre (cada producto
// siempre con el mismo color, como en sistema-botica).
function fondo (p) {
  if (p.miniatura_url) {
    return { backgroundImage: `url(${p.miniatura_url})`, backgroundSize: 'cover', backgroundPosition: 'center' }
  }
  const tono = ((p.nombre || 'A').charCodeAt(0) * 23) % 360
  return { background: `linear-gradient(180deg, hsl(${tono} 60% 96%), hsl(${tono} 45% 90%))` }
}

function conStock (p) {
  return p.variantes.filter((v) => v.stock > 0)
}

// Presentaciones con stock en el orden en que se cargaron, sin repetir (la
// misma en dos colores sale una vez). Hasta 4: la tarjeta no es la ficha.
function presentacionesDisponibles (p) {
  return [...new Set(conStock(p).map((v) => v.presentacion).filter(Boolean))].slice(0, 4)
}

function coloresDisponibles (p) {
  const vistos = new Map()
  conStock(p).forEach((v) => { if (v.color) vistos.set(v.color.nombre, v.color) })
  return [...vistos.values()].slice(0, 6)
}

// "S/ 40.00" o "S/ 40.00 – 45.00" si las variantes tienen precios distintos.
// `campo`: 'precio' (el de hoy, con oferta) o 'precio_lista' (para tachar).
function rangoPrecio (p, campo = 'precio') {
  const precios = p.variantes.map((v) => Number(v[campo]))
  if (!precios.length) return formatearPrecio(p.precio)
  const min = Math.min(...precios)
  const max = Math.max(...precios)
  return min === max ? formatearPrecio(min) : `${formatearPrecio(min)} – ${max.toFixed(2)}`
}

defineExpose({ refrescar: () => cargar(1) })
</script>

<style lang="scss" scoped>
.catalogo__lista--grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(168px, 1fr));
  gap: 12px;
}

.catalogo__lista--list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.tarjeta-envoltura {
  position: relative;
  display: flex;

  > .tarjeta {
    flex: 1;
    min-width: 0;
  }
}

.tarjeta__info {
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
  color: var(--q-dark, #fff);
  cursor: pointer;

  &:hover {
    color: $primary;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }

  // En lista la imagen es chica: va a la izquierda del "+".
  .tarjeta-envoltura--list & {
    top: auto;
    right: 48px;
    bottom: 12px;
  }
}

.tarjeta {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: 0;
  border: 1px solid var(--app-border-subtle);
  border-radius: 14px;
  background: var(--app-surface);
  text-align: left;
  color: inherit;
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;

  &:hover:not(:disabled) {
    border-color: rgba($primary, 0.5);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }

  &--agotada {
    cursor: not-allowed;
    opacity: 0.55;
  }

  &--list {
    flex-direction: row;
    align-items: stretch;
  }
}

.tarjeta__img {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1 / 1;

  .tarjeta--list & {
    flex: 0 0 72px;
    aspect-ratio: auto;
  }
}

.tarjeta__ph {
  color: rgba(0, 0, 0, 0.25);
}

.tarjeta__stock {
  position: absolute;
  top: 8px;
  left: 8px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: #1F2937;

  .tarjeta--list & {
    display: none;
  }
}

.tarjeta__punto {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.tarjeta__stock--alto .tarjeta__punto { background: #16A34A; }
.tarjeta__stock--medio .tarjeta__punto { background: #0D9488; }
.tarjeta__stock--bajo .tarjeta__punto { background: #D97706; }
.tarjeta__stock--agotado .tarjeta__punto { background: #9CA3AF; }

.tarjeta__vendidos {
  position: absolute;
  bottom: 8px;
  left: 8px;
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 2px 7px;
  border-radius: 999px;
  background: rgba(17, 24, 39, 0.72);
  font-size: 10px;
  font-weight: 600;
  color: #FFFFFF;
}

.tarjeta__enCarrito {
  position: absolute;
  top: 44px;
  right: 8px;
  min-width: 28px;
  padding: 3px 8px;
  border-radius: 999px;
  background: $primary;
  font-size: 12px;
  font-weight: 700;
  text-align: center;
  color: #FFFFFF;
}

.tarjeta__oferta {
  position: absolute;
  right: 8px;
  bottom: 8px;
  padding: 3px 8px;
  border-radius: 6px;
  background: #DC2626;
  font-size: 11.5px;
  font-weight: 800;
  color: #FFFFFF;
  box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35);
}

.tarjeta__cuerpo {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 4px;
  padding: 10px 12px 12px;
}

.tarjeta__nombre {
  display: -webkit-box;
  overflow: hidden;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  font-size: 13.5px;
  font-weight: 600;
  line-height: 1.3;
  color: var(--app-ink);
}

.tarjeta__categoria {
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.tarjeta__variantes {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  margin-top: 2px;
}

.tarjeta__presentacion {
  max-width: 100%;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  padding: 0 5px;
  border: 1px solid var(--app-border-control);
  border-radius: 4px;
  font-size: 10.5px;
  font-weight: 600;
  line-height: 16px;
  color: var(--app-ink-2);
}

.tarjeta__color {
  width: 12px;
  height: 12px;
  border: 1px solid var(--app-border-control);
  border-radius: 50%;
}

.tarjeta__pie {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 8px;
}

.tarjeta__precios {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
}

.tarjeta__lista {
  font-size: 11px;
  color: var(--app-ink-2);
}

.tarjeta__precio {
  font-size: 14px;
  font-weight: 700;
  color: var(--app-ink);

  &--oferta {
    color: #DC2626;
  }
}

.tarjeta__agregar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: rgba($primary, 0.1);
  color: $primary;
}

.catalogo__vacio {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 64px 16px;
  font-size: 13px;
  text-align: center;
  color: var(--app-ink-2);
}

.catalogo__vacioTitulo {
  font-size: 15px;
  font-weight: 600;
  color: var(--app-ink);
}

.catalogo__cargando,
.catalogo__mas {
  display: flex;
  justify-content: center;
  padding: 16px;
}
</style>
