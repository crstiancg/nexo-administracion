<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Etiquetas"
      :subtitle="`${seleccionadas.length} ${seleccionadas.length === 1 ? 'variante seleccionada' : 'variantes seleccionadas'} · ${total} ${total === 1 ? 'etiqueta' : 'etiquetas'}`"
    >
      <template #actions>
        <AppButton
          v-if="seleccionadas.length"
          variant="tertiary"
          label="Limpiar selección"
          @click="seleccionadas = []"
        />
        <AppButton
          variant="primary"
          icon="print"
          :label="total ? `Imprimir ${total}` : 'Imprimir'"
          :disable="!total"
          @click="imprimir"
        />
      </template>
    </AppPageHeader>

    <!-- ══ FORMATO ══ -->
    <AppCard class="etiquetas-opciones">
      <div class="etiquetas-opciones__campos">
        <q-select
          v-model="formatoId"
          :options="FORMATOS"
          option-value="id"
          option-label="label"
          emit-value
          map-options
          dense
          outlined
          label="Tamaño de etiqueta"
          class="etiquetas-control etiquetas-opciones__formato"
        />
        <q-input
          v-if="formato.tipo === 'a4'"
          v-model.number="inicio"
          type="number"
          min="1"
          :max="porHoja(formato)"
          dense
          outlined
          label="Empezar en el sticker n°"
          class="etiquetas-control etiquetas-opciones__inicio"
        >
          <q-tooltip>Para aprovechar una hoja ya empezada: los anteriores quedan en blanco.</q-tooltip>
        </q-input>
        <p class="etiquetas-opciones__ayuda">
          <template v-if="formato.tipo === 'a4' && total">
            {{ hojas }} {{ hojas === 1 ? 'hoja' : 'hojas' }}.
          </template>
          Al imprimir elegí <strong>Márgenes: ninguno</strong> y <strong>Escala: 100 %</strong>.
          Para un PDF, elegí <strong>Guardar como PDF</strong> como impresora.
        </p>
      </div>

      <div
        v-if="seleccionadas.length"
        class="etiquetas-opciones__previa"
      >
        <span class="etiquetas-opciones__previaTitulo">Vista previa (tamaño real)</span>
        <div class="etiquetas-opciones__papel">
          <EtiquetaImpresa
            :variante="seleccionadas[0]"
            :formato="formato"
          />
        </div>
      </div>
    </AppCard>

    <!-- ══ VARIANTES ══ -->
    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por producto, SKU o código de barras"
      :has-active-filters="hayFiltros"
      :refreshing="loading"
      @clear="limpiarFiltros"
      @refresh="tableRef.actualizar()"
    >
      <q-chip
        v-if="productoFiltro"
        removable
        dense
        class="etiquetas-chip"
        @remove="productoFiltro = null"
      >
        Producto: {{ productoFiltro.nombre }}
      </q-chip>
      <AppButton
        v-if="puedeSeleccionarTodo"
        variant="tertiary"
        icon="done_all"
        :label="`Seleccionar los ${pagination.rowsNumber} resultados`"
        :loading="seleccionandoTodo"
        @click="seleccionarTodo"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      v-model:selected="seleccionadas"
      selection="multiple"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="No hay variantes que coincidan."
      @request="onRequest"
    >
      <template #body-cell-codigo="props">
        <q-td
          :props="props"
          class="text-mono"
        >
          {{ props.row.codigo_barras }}
        </q-td>
      </template>

      <template #body-cell-producto="props">
        <q-td :props="props">
          <div class="etiquetas-producto">
            {{ props.row.producto.nombre }}
          </div>
          <div class="etiquetas-detalle text-mono">
            {{ props.row.sku }}
          </div>
        </q-td>
      </template>

      <template #body-cell-color="props">
        <q-td :props="props">
          <span class="etiquetas-color">
            <span
              v-if="props.row.color"
              class="etiquetas-color__swatch"
              :style="{ background: props.row.color.hexadecimal }"
            />
            {{ props.row.color?.nombre ?? '—' }}
          </span>
        </q-td>
      </template>

      <template #body-cell-cantidad="props">
        <q-td :props="props">
          <q-input
            :model-value="cantidadDe(props.row)"
            type="number"
            min="0"
            step="1"
            dense
            outlined
            hide-bottom-space
            :aria-label="`Etiquetas de ${props.row.producto.nombre} ${props.row.presentacion} ${props.row.color?.nombre ?? ''}`"
            class="etiquetas-control etiquetas-cantidad"
            @update:model-value="cambiarCantidad(props.row, $event)"
          />
        </q-td>
      </template>
    </AppTable>

    <ImpresionEtiquetas ref="impresion" />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import EtiquetaService from '@/services/EtiquetaService'
import EtiquetaImpresa from './EtiquetaImpresa.vue'
import ImpresionEtiquetas from './ImpresionEtiquetas.vue'
import { FORMATOS, expandir, formatoPorId, paginar, porHoja } from './formatos'

const $q = useQuasar()
const route = useRoute()

// Más de esto en una sola impresión casi seguro es un error de tipeo
// (un 1000 en vez de un 10): se pide confirmar.
const MUCHAS = 300
// Tope de "seleccionar todos los resultados": un lote, no el catálogo entero.
const MAX_LOTE = 500

const columns = [
  { name: 'codigo', label: 'Código', field: 'codigo_barras', align: 'left' },
  { name: 'producto', label: 'Producto', field: (row) => row.producto.nombre, align: 'left' },
  { name: 'presentacion', label: 'Presentación', field: 'presentacion', align: 'left' },
  { name: 'color', label: 'Color', field: (row) => row.color?.nombre, align: 'left' },
  { name: 'stock', label: 'Stock', field: 'stock', align: 'right', classes: 'text-mono' },
  { name: 'cantidad', label: 'Etiquetas', field: 'id', align: 'left' }
]

// ── Filtros ──
const search = ref('')
const busqueda = ref('')
// Al llegar desde Productos (/etiquetas?producto=5) la tabla muestra sólo ese.
const productoFiltro = ref(null)

let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = (value ?? '').trim() }, 400)
})

const hayFiltros = computed(() => Boolean(search.value || productoFiltro.value))

function limpiarFiltros () {
  search.value = ''
  productoFiltro.value = null
}

// AppTable vuelve a la página 1 y pide datos cuando cambia `filter`.
const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, producto: productoFiltro.value?.id ?? null }))

function filtros () {
  const params = {}
  if (busqueda.value) params.search = busqueda.value
  if (productoFiltro.value) params.producto_id = productoFiltro.value.id
  return params
}

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage } = requested
  loading.value = true
  try {
    const { data, total = 0 } = await EtiquetaService.getData({ params: { ...filtros(), page, rowsPerPage } })
    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } catch {
    $q.notify({ type: 'negative', message: 'No se pudieron cargar las variantes.', position: 'top-right' })
  } finally {
    loading.value = false
  }
}

// ── Selección y cantidades ──
// La selección sobrevive a cambiar de página o de búsqueda: así se arma un
// lote con variantes de varios productos y se imprime todo junto.
const seleccionadas = ref([])
// Cantidad elegida por variante (id → n). Sin tocar es 1, NO el stock: una
// variante con 994 unidades marcada de pasada serían 994 etiquetas.
const cantidades = reactive({})

function cantidadDe (variante) {
  return cantidades[variante.id] ?? 1
}

function cambiarCantidad (variante, valor) {
  const n = Math.max(0, Math.trunc(Number(valor) || 0))
  cantidades[variante.id] = n
  // Escribir una cantidad es querer imprimirla: se marca sola.
  if (n > 0 && !seleccionadas.value.some((v) => v.id === variante.id)) {
    seleccionadas.value = [...seleccionadas.value, variante]
  }
}

const puedeSeleccionarTodo = computed(() =>
  hayFiltros.value &&
  pagination.value.rowsNumber > rows.value.length &&
  pagination.value.rowsNumber <= MAX_LOTE)

const seleccionandoTodo = ref(false)
async function seleccionarTodo () {
  seleccionandoTodo.value = true
  try {
    const { data } = await EtiquetaService.getData({ params: { ...filtros(), rowsPerPage: 0 } })
    agregarASeleccion(data)
  } catch {
    $q.notify({ type: 'negative', message: 'No se pudieron seleccionar los resultados.', position: 'top-right' })
  } finally {
    seleccionandoTodo.value = false
  }
}

function agregarASeleccion (variantes) {
  const ya = new Set(seleccionadas.value.map((v) => v.id))
  seleccionadas.value = [...seleccionadas.value, ...variantes.filter((v) => !ya.has(v.id))]
}

// ── Formato (se recuerda en este navegador) ──
const CLAVE_FORMATO = 'etiquetas.formato'
function leerFormato () {
  try {
    return localStorage.getItem(CLAVE_FORMATO)
  } catch {
    return null
  }
}
const formatoId = ref(formatoPorId(leerFormato()).id)
const formato = computed(() => formatoPorId(formatoId.value))
const inicio = ref(1)

watch(formatoId, (id) => {
  try {
    localStorage.setItem(CLAVE_FORMATO, id)
  } catch {
    // Sin almacenamiento sólo se pierde la preferencia.
  }
  inicio.value = 1
})

// Una etiqueta por unidad, en el orden en que se seleccionaron.
const etiquetas = computed(() => expandir(seleccionadas.value.map((v) => ({ variante: v, cantidad: cantidadDe(v) }))))
const total = computed(() => etiquetas.value.length)
const hojas = computed(() => paginar(etiquetas.value, formato.value, inicio.value).length)

// ── Imprimir ──
const impresion = ref(null)

function imprimir () {
  const enviar = () => impresion.value.imprimir(etiquetas.value, formato.value, inicio.value)
  if (total.value <= MUCHAS) return enviar()

  $q.dialog({
    title: 'Muchas etiquetas',
    message: `Vas a imprimir ${total.value} etiquetas. ¿Seguro?`,
    cancel: { label: 'Revisar', flat: true, noCaps: true },
    ok: { label: 'Imprimir', noCaps: true, unelevated: true },
    persistent: true
  }).onOk(enviar)
}

onMounted(async () => {
  const productoId = Number(route.query.producto)
  if (productoId) {
    // Desde Productos: se filtra por ese producto y se marcan todas sus variantes.
    try {
      const { data } = await EtiquetaService.getData({ params: { producto_id: productoId, rowsPerPage: 0 } })
      if (data.length) {
        productoFiltro.value = data[0].producto
        agregarASeleccion(data)
      }
    } catch {
      $q.notify({ type: 'negative', message: 'No se pudieron cargar las variantes del producto.', position: 'top-right' })
    }
  }
  // Con productoFiltro ya puesto, el cambio de `filter` dispara el pedido;
  // si no cambió nada, se pide acá.
  if (!productoFiltro.value) tableRef.value.requestServerInteraction()
})
</script>

<style lang="scss" scoped>
.etiquetas-opciones {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 20px;
  padding: 18px 20px;
}

.etiquetas-opciones__campos {
  display: flex;
  flex: 1 1 420px;
  flex-wrap: wrap;
  gap: 12px;
}

.etiquetas-opciones__formato {
  flex: 1 1 260px;
}

.etiquetas-opciones__inicio {
  flex: 0 1 190px;
}

.etiquetas-opciones__ayuda {
  flex-basis: 100%;
  margin: 0;
  font-size: 12.5px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.etiquetas-opciones__previa {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 100%;
}

.etiquetas-opciones__previaTitulo {
  font-size: 12px;
  font-weight: 600;
  color: var(--app-ink-2);
}

// La etiqueta es blanca y negra siempre (es papel), también en tema oscuro.
.etiquetas-opciones__papel {
  display: flex;
  justify-content: center;
  padding: 12px;
  overflow-x: auto;
  border-radius: 10px;
  background: var(--app-page);
  border: 1px dashed var(--app-border-control);

  > * {
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.18);
  }
}

// Mismo radio y borde que AppTextField.
.etiquetas-control {
  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }

  &.q-field--focused :deep(.q-field__control) {
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }
}

.etiquetas-cantidad {
  width: 90px;
}

.etiquetas-chip {
  background: var(--app-brand-soft);
  color: var(--app-brand-soft-ink);
  font-weight: 600;
}

.etiquetas-producto {
  font-weight: 600;
}

.etiquetas-detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.etiquetas-color {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.etiquetas-color__swatch {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
  border-radius: 999px;
  border: 1px solid var(--app-border-control);
}
</style>
