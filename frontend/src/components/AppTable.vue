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
      :grid="enTarjetas"
      :class="['app-table__table', { 'app-table__table--tarjetas': enTarjetas }]"
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

      <!-- Celular: cada fila es una tarjeta. Reusa las celdas que define
           cada lista (body-cell-*). Arriba el título (1ra columna) y el
           estado; al medio los datos en dos columnas; abajo el monto
           destacado y las acciones. Ver partesDe(). -->
      <template
        v-if="enTarjetas"
        #item="item"
      >
        <div :class="['app-table__tarjeta', { 'app-table__tarjeta--seleccionada': item.selected }]">
          <div class="app-table__tarjetaCabecera">
            <q-checkbox
              v-if="selection !== 'none'"
              v-model="item.selected"
              dense
              :aria-label="`Seleccionar fila ${item.rowIndex + 1}`"
            />
            <div class="app-table__tarjetaTitulo">
              <component
                :is="celda(item, partesDe(item).titulo)"
                v-if="partesDe(item).titulo"
              />
            </div>
            <div
              v-if="partesDe(item).estado"
              class="app-table__tarjetaEstado"
            >
              <component :is="celda(item, partesDe(item).estado)" />
            </div>
          </div>

          <div
            v-if="partesDe(item).datos.length"
            class="app-table__tarjetaDatos"
          >
            <div
              v-for="col in partesDe(item).datos"
              :key="col.name"
              :class="['app-table__dato', { 'app-table__dato--ancho': col.tarjeta === 'ancho' }]"
            >
              <span class="app-table__datoRotulo">{{ col.label }}</span>
              <div class="app-table__datoValor">
                <component :is="celda(item, col)" />
              </div>
            </div>
          </div>

          <div
            v-if="partesDe(item).monto || partesDe(item).acciones"
            class="app-table__tarjetaPie"
          >
            <div
              v-if="partesDe(item).monto"
              class="app-table__monto"
            >
              <span class="app-table__datoRotulo">{{ partesDe(item).monto.label }}</span>
              <div class="app-table__montoValor">
                <component :is="celda(item, partesDe(item).monto)" />
              </div>
            </div>
            <div
              v-if="partesDe(item).acciones"
              class="app-table__tarjetaAcciones"
            >
              <component :is="celda(item, partesDe(item).acciones)" />
            </div>
          </div>
        </div>
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
import { computed, h, useSlots, watch } from 'vue'
import { useQuasar } from 'quasar'
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
  },

  // En el celular las filas se ven como tarjetas. false = siempre tabla.
  tarjetasEnMovil: {
    type: Boolean,
    default: true
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
const $q = useQuasar()

// ── Tarjetas (celular) ──
// Sin información de pantalla (tests, SSR) va la tabla.
const enTarjetas = computed(() => props.tarjetasEnMovil && Boolean($q?.screen?.lt.sm))

// Columnas que se reconocen solas por el nombre. Una lista puede decidirlo
// a mano con `tarjeta` en la columna: 'estado' | 'monto' | 'ancho' | 'oculto'.
const NOMBRES_ESTADO = ['estado', 'activo', 'status']
const NOMBRES_MONTO = ['saldo', 'total', 'monto', 'importe', 'precio_venta', 'precio']

/**
 * Reparte las columnas de la fila en las partes de la tarjeta: título (la
 * primera), estado (arriba a la derecha), datos (al medio), monto (abajo,
 * grande) y acciones (abajo a la derecha). Lo que una lista marca como
 * `tarjeta: 'oculto'` no aparece en el celular.
 */
function partesDe (item) {
  const cols = item.cols.filter((col) => col.tarjeta !== 'oculto')
  const ultima = cols[cols.length - 1]
  const acciones = ultima && cols.length > 1 && (ultima.name === 'acciones' || !ultima.label) ? ultima : null
  const resto = cols.filter((col) => col !== acciones)
  const titulo = resto[0] ?? null

  const elegir = (tipo, nombres) => resto.find((col) => col !== titulo && col.tarjeta === tipo) ??
    [...resto].reverse().find((col) => col !== titulo && !col.tarjeta && nombres.includes(col.name)) ??
    null
  const estado = elegir('estado', NOMBRES_ESTADO)
  const monto = elegir('monto', NOMBRES_MONTO)

  return {
    titulo,
    estado,
    monto,
    acciones,
    datos: resto.filter((col) => ![titulo, estado, monto].includes(col))
  }
}

// La celda de una columna: la que define la lista (body-cell-<nombre>, que
// usa <q-td :props>) o, si no hay, el valor ya formateado.
function celda (item, col) {
  const slot = slots[`body-cell-${col.name}`]
  if (!slot) return () => h('span', col.value)
  const scope = { key: item.key, row: item.row, rowIndex: item.rowIndex, pageIndex: item.pageIndex, col, value: col.value, selected: item.selected }
  return () => slot(scope)
}

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

  // La fila seleccionada lleva el tinte de la marca.
  :deep(tbody tr.selected) {
    background: var(--app-brand-soft);
  }
}

// ── Tarjetas (celular) ──
.app-table__table--tarjetas {
  // Dos tarjetas por fila (una sola en pantallas muy angostas). Las de una
  // fila quedan del mismo alto, con el pie abajo.
  :deep(.q-table__grid-content) {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    padding: 12px;

    @media (max-width: 359px) {
      grid-template-columns: minmax(0, 1fr);
    }
  }
}

.app-table__tarjeta {
  display: flex;
  flex-direction: column;
  gap: 0;
  width: 100%;
  height: 100%;
  padding: 14px 14px 14px;
  overflow: hidden;
  container: tarjeta / inline-size;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
  background: var(--app-surface);
  box-shadow: var(--app-shadow-card);

  // Las celdas de cada lista vienen como <td>: acá son bloques sueltos.
  :deep(td) {
    display: block;
    padding: 0 !important;
    border: 0 !important;
    height: auto !important;
    text-align: left !important;
    white-space: normal !important;
  }
}

.app-table__tarjeta--seleccionada {
  border-color: var(--q-primary);
  background: var(--app-brand-soft);
}

// Cabecera: título fuerte a la izquierda, estado arriba a la derecha.
.app-table__tarjetaCabecera {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding-bottom: 12px;
}

.app-table__tarjetaTitulo {
  flex: 1;
  min-width: 0;
  font-size: 15.5px;
  font-weight: 700;
  line-height: 1.3;
  color: var(--app-ink);
}

.app-table__tarjetaEstado {
  flex-shrink: 0;
}

// Datos: dos columnas, el rótulo chico encima del valor (se lee de arriba
// abajo, como una ficha). Uno largo puede ocupar el ancho entero.
.app-table__tarjetaDatos {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 14px;
  padding: 12px 0;
  border-top: 1px solid var(--app-border-subtle);
}

.app-table__dato {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}

.app-table__dato--ancho {
  grid-column: 1 / -1;
}

.app-table__datoRotulo {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.4px;
  text-transform: uppercase;
  color: var(--app-ink-2);
}

.app-table__datoValor {
  font-size: 13.5px;
  line-height: 1.4;
  color: var(--app-ink);
  overflow-wrap: anywhere;
}

// Pie: el monto en grande a la izquierda y las acciones a la derecha.
.app-table__tarjetaPie {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  margin: auto -14px -14px;
  padding: 10px 14px;
  border-top: 1px solid var(--app-border-subtle);
  border-radius: 0 0 12px 12px;
  background: var(--app-page);
}

.app-table__monto {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.app-table__montoValor {
  font-size: 17px;
  font-weight: 700;
  color: var(--app-ink);

  // Las celdas de monto traen su texto chico de apoyo ("de S/ 408.00"):
  // queda chico; lo grande es el número.
  :deep(.os-meta),
  :deep([class*='meta']) {
    font-size: 11.5px;
    font-weight: 500;
  }
}

.app-table__tarjetaAcciones {
  display: flex;
  flex-shrink: 0;
  justify-content: flex-end;
  margin-left: auto;

  // Botones de acción más cómodos para el pulgar.
  :deep(.q-btn) {
    min-width: 36px;
    min-height: 36px;
  }
}

// Tarjeta angosta (dos por fila en el celular): todo en una columna.
@container tarjeta (max-width: 299px) {
  .app-table__tarjetaCabecera {
    flex-wrap: wrap;
    gap: 6px;
  }

  .app-table__tarjetaTitulo {
    flex-basis: 100%;
    font-size: 14px;
  }

  .app-table__tarjetaDatos {
    grid-template-columns: minmax(0, 1fr);
    gap: 10px;
  }

  .app-table__tarjetaPie {
    flex-wrap: wrap;
    gap: 6px;
  }

  .app-table__montoValor {
    font-size: 15px;
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
  background: var(--q-primary);
  color: #FFFFFF;

  &:hover {
    background: var(--q-primary);
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
