<template>
  <AppCard class="app-table">
    <q-table
      v-model:pagination="paginationModel"
      v-model:selected="selectedModel"
      :rows="rows"
      :columns="columns"
      :row-key="rowKey"
      :selection="selection"
      :loading="loading"
      flat
      hide-bottom
      binary-state-sort
      class="app-table__table"
      @request="onTableRequest"
    >
      <template
        v-for="(_, name) in forwardedSlots"
        #[name]="slotProps"
        :key="name"
      >
        <slot
          :name="name"
          v-bind="slotProps ?? {}"
        />
      </template>

      <template
        v-if="!$slots['no-data']"
        #no-data
      >
        <div class="app-table__empty">{{ noDataLabel }}</div>
      </template>
    </q-table>

    <!-- Paginación propia, no la de QTable: la de Quasar viene en inglés
         ("Records per page") y como controles sueltos. Esta es la misma
         barra en todos lados donde se use esta tabla — números de página,
         "Mostrando X–Y de Z", activa en el rojo de marca. -->
    <div
      v-if="rows.length > 0"
      class="app-table__pagination"
    >
      <div class="app-table__paginationInfo">
        Mostrando <strong>{{ rangeStart }}–{{ rangeEnd }}</strong> de <strong>{{ totalRows }}</strong>
      </div>

      <div class="app-table__paginationControls">
        <q-btn
          flat
          dense
          round
          icon="chevron_left"
          size="sm"
          :disable="paginationModel.page <= 1"
          aria-label="Página anterior"
          @click="goToPage(paginationModel.page - 1)"
        />

        <template
          v-for="(item, index) in pageItems"
          :key="`${item}-${index}`"
        >
          <div
            v-if="item === '…'"
            class="app-table__pageEllipsis"
          >
            …
          </div>

          <div
            v-else
            :class="['app-table__pageNumber', { 'app-table__pageNumber--active': item === paginationModel.page }]"
            @click="goToPage(item)"
          >
            {{ item }}
          </div>
        </template>

        <q-btn
          flat
          dense
          round
          icon="chevron_right"
          size="sm"
          :disable="paginationModel.page >= totalPages"
          aria-label="Página siguiente"
          @click="goToPage(paginationModel.page + 1)"
        />
      </div>
    </div>
  </AppCard>
</template>

<script setup>
import { computed, useSlots, watch } from 'vue'
import AppCard from './AppCard.vue'

const props = defineProps({
  rows: {
    type: Array,
    required: true
  },

  columns: {
    type: Array,
    required: true
  },

  rowKey: {
    type: String,
    default: 'id'
  },

  // 'none' | 'single' | 'multiple' — mismos valores que QTable, sin
  // reinventar el vocabulario.
  selection: {
    type: String,
    default: 'none'
  },

  noDataLabel: {
    type: String,
    default: 'No hay resultados para mostrar.'
  },

  loading: {
    type: Boolean,
    default: false
  },

  // Sólo en modo servidor: el término de búsqueda que viaja en `request`.
  filter: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['request', 'refresh'])

// v-model con default: quien use la tabla sólo declara `rows`/`columns` si
// no le importa controlar página o selección desde afuera. Quien sí
// necesita leerlas (por ejemplo, para acciones sobre la selección) pasa
// v-model:selected o v-model:pagination y listo.
const paginationModel = defineModel('pagination', {
  default: () => ({ page: 1, rowsPerPage: 8 })
})

const selectedModel = defineModel('selected', {
  default: () => []
})

const slots = useSlots()

// El slot no-data lo resuelve el componente por default (texto en
// castellano, ver noDataLabel); si quien lo usa manda su propio slot
// no-data, ese gana y no lo reenviamos duplicado.
const forwardedSlots = computed(() => {
  const { 'no-data': _noData, ...rest } = slots
  return rest
})

// Modo servidor con la misma convención que QTable: si la paginación trae
// `rowsNumber`, las filas son sólo la página actual y el total lo sabe la API.
const serverSide = computed(() => paginationModel.value.rowsNumber !== undefined)
const totalRows = computed(() => (serverSide.value ? paginationModel.value.rowsNumber : props.rows.length))

const totalPages = computed(() => Math.max(1, Math.ceil(totalRows.value / paginationModel.value.rowsPerPage)))

// Ventana de páginas: primera, última, la actual y una vecina de cada lado,
// con "…" en los huecos. Sin esto, 10.000 filas a 8 por página son 1.250
// números renderizados de una — esto la deja en un ancho constante sin
// importar cuántas páginas haya.
const pageItems = computed(() => {
  const total = totalPages.value
  const current = paginationModel.value.page
  const delta = 1

  const shown = []
  for (let page = 1; page <= total; page++) {
    if (page === 1 || page === total || Math.abs(page - current) <= delta) {
      shown.push(page)
    }
  }

  const items = []
  let previous = null
  for (const page of shown) {
    if (previous !== null && page - previous > 1) {
      items.push('…')
    }
    items.push(page)
    previous = page
  }

  return items
})
const rangeStart = computed(() => (totalRows.value === 0 ? 0 : (paginationModel.value.page - 1) * paginationModel.value.rowsPerPage + 1))
const rangeEnd = computed(() => Math.min(paginationModel.value.page * paginationModel.value.rowsPerPage, totalRows.value))

function requestPage (pagination) {
  emit('request', { pagination, filter: props.filter })
}

// QTable no re-slice las filas si sólo mutás `pagination.value.page`: guarda
// una copia interna del objeto y sólo la actualiza cuando la referencia
// cambia (confirmado en la doc de QTable). Por eso ir a una página SIEMPRE
// reemplaza el objeto entero en vez de tocar una propiedad suelta.
function goToPage (page) {
  // En modo servidor la página nueva la trae el padre desde la API; la
  // paginación se actualiza cuando llega la respuesta, no antes.
  if (serverSide.value) {
    requestPage({ ...paginationModel.value, page })
    return
  }
  paginationModel.value = { ...paginationModel.value, page }
}

/** Vuelve a pedir la página actual; el padre lo llama tras crear/editar/borrar. */
function requestServerInteraction () {
  requestPage({ ...paginationModel.value })
}

// Botón ⟳ de AppFilterBar (la lista lo conecta con tableRef.actualizar()):
// vuelve a traer la página actual de la BD sin perder filtros ni página, y
// con @refresh avisa para recargar lo que la lista cargó aparte en su
// onMounted (catálogos de los filtros: categorías, sedes…).
function actualizar () {
  requestServerInteraction()
  emit('refresh')
}

// En modo servidor QTable emite `request` al ordenar por una columna en vez
// de ordenar local; se reenvía con el filtro actual.
function onTableRequest ({ pagination }) {
  requestPage(pagination)
}

watch(() => props.filter, () => {
  if (serverSide.value) requestPage({ ...paginationModel.value, page: 1 })
})

// Si filtrar/buscar deja menos páginas de las que tenías, sin esto quedás
// mirando una página vacía en vez de volver a la primera. Sólo en modo
// cliente: en modo servidor las filas nuevas SON la respuesta al pedido de
// una página, y resetear dispararía otro pedido en bucle.
watch(() => props.rows, () => {
  if (!serverSide.value) goToPage(1)
})

defineExpose({ requestServerInteraction, actualizar })
</script>

<style lang="scss" scoped>
.app-table {
  overflow: hidden;
}

.app-table__table {
  background: transparent;

  :deep(thead th) {
    background: var(--app-page);
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: var(--app-ink-2);
  }

  :deep(tbody td) {
    font-size: 13.5px;
    color: var(--app-ink);
  }

  // El tinte de la fila seleccionada es el mismo del negativo suave: no es
  // casualidad, es la única tinta rojiza que el sistema ya tiene definida
  // para los dos temas.
  :deep(tbody tr.selected) {
    background: var(--app-negative-soft);
  }
}

.app-table__empty {
  padding: 32px;
  text-align: center;
  font-size: 13.5px;
  color: var(--app-ink-2);
}

.app-table__pagination {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border-top: 1px solid var(--app-border-subtle);
}

.app-table__paginationInfo {
  font-size: 13px;
  color: var(--app-ink-2);

  strong {
    color: var(--app-ink);
  }
}

.app-table__paginationControls {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 5px;
}

.app-table__pageNumber {
  min-width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: $font-mono;
  font-variant-numeric: tabular-nums;
  font-size: 13px;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    background: var(--app-page);
  }
}

.app-table__pageNumber--active {
  background: $primary;
  color: #FFFFFF;

  &:hover {
    background: $primary;
  }
}

.app-table__pageEllipsis {
  min-width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--app-ink-2);
}
</style>
