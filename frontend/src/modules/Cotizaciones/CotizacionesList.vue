<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Cotizaciones"
      :subtitle="`${pagination.rowsNumber} cotizaciones`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('cotizaciones.store')"
          variant="primary"
          label="Nueva cotización"
          icon="request_quote"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por código, cliente o documento"
      :has-active-filters="hayFiltros"
      :refreshing="loading"
      @clear="limpiarFiltros"
      @refresh="tableRef.actualizar()"
    >
      <AppFilterPill
        v-model="estadoFilter"
        label="Estado"
        :options="estadoOptions"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="No hay cotizaciones que coincidan."
      @request="onRequest"
    >
      <template #body-cell-codigo="props">
        <q-td :props="props">
          <button
            type="button"
            class="cot-codigo text-mono"
            @click="ver(props.row)"
          >
            {{ props.row.codigo }}
          </button>
          <div class="cot-meta">
            {{ fechaCorta(props.row.fecha) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-cliente="props">
        <q-td :props="props">
          <div v-if="props.row.cliente">
            {{ props.row.cliente.nombre }}
          </div>
          <div
            v-else
            class="cot-meta"
          >
            Sin cliente
          </div>
          <div
            v-if="props.row.cliente?.numero_documento"
            class="cot-meta"
          >
            {{ props.row.cliente.tipo_documento }} {{ props.row.cliente.numero_documento }}
          </div>
        </q-td>
      </template>

      <template #body-cell-estado="props">
        <q-td :props="props">
          <AppChip
            :status="ESTADOS[props.row.estado].status"
            :label="ESTADOS[props.row.estado].label"
          />
          <div
            v-if="props.row.pedido"
            class="cot-meta text-mono"
          >
            → {{ props.row.pedido.codigo }}
          </div>
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <q-btn
            flat
            dense
            round
            icon="visibility"
            size="sm"
            color="grey-7"
            :aria-label="`Ver ${props.row.codigo}`"
            @click="ver(props.row)"
          />
          <q-btn
            v-if="props.row.editable && userStore.hasPermission('cotizaciones.update')"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar ${props.row.codigo}`"
            @click="editar(props.row)"
          />
        </q-td>
      </template>
    </AppTable>

    <!-- ── Crear / editar ── -->
    <AppDialog
      v-model="formDialog"
      :title="editId ? 'Editar cotización' : 'Nueva cotización'"
      size="lg"
      persistent
    >
      <CotizacionForm
        :id="editId"
        ref="formRef"
        :key="editId ?? `nueva-${aperturas}`"
        @save="guardada"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="formDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar"
          :loading="formRef?.form.processing"
          @click="formRef.submit()"
        />
      </template>
    </AppDialog>

    <!-- ── Detalle con acciones ── -->
    <AppDialog
      v-model="detalleDialog"
      title="Cotización"
      size="lg"
    >
      <CotizacionDetalle
        v-if="detalleId"
        :id="detalleId"
        ref="detalleRef"
        :key="detalleId"
      />

      <template #actions>
        <AppButton
          v-if="detalle"
          variant="tertiary"
          label="Imprimir / PDF"
          icon="print"
          @click="impresionRef.imprimir(detalle)"
        />
        <AppButton
          v-if="detalle"
          variant="tertiary"
          label="Excel"
          icon="mdi-microsoft-excel"
          :loading="accionando === 'excel'"
          @click="bajarExcel"
        />
        <AppButton
          v-if="pendiente && userStore.hasPermission('cotizaciones.rechazar')"
          variant="tertiary"
          label="Rechazada"
          icon="thumb_down"
          :loading="accionando === 'rechazar'"
          @click="ejecutar('rechazar')"
        />
        <AppButton
          v-if="pendiente && userStore.hasPermission('cotizaciones.update')"
          variant="secondary"
          :label="detalle.estado === 'vencida' ? 'Renovar' : 'Editar'"
          icon="edit"
          @click="editar(detalle)"
        />
        <AppButton
          v-if="detalle?.estado === 'pendiente' && userStore.hasPermission('cotizaciones.convertir')"
          variant="primary"
          label="Convertir en pedido"
          icon="shopping_cart_checkout"
          @click="abrirConvertir"
        />
      </template>
    </AppDialog>

    <!-- ── Convertir: el cliente puede no llevar todo lo cotizado ── -->
    <AppDialog
      v-model="convertirDialog"
      :title="`Convertir ${detalle?.codigo ?? ''} en pedido`"
      size="lg"
      persistent
    >
      <p class="conv-ayuda">
        Desmarcá lo que no lleva y bajá la cantidad si pide menos. Los precios son los cotizados.
      </p>

      <div class="conv-tablaWrap">
        <table class="conv-tabla">
          <thead>
            <tr>
              <th />
              <th>Producto</th>
              <th class="text-right">
                Cotizado
              </th>
              <th>Pide</th>
              <th class="text-right">
                Precio
              </th>
              <th class="text-right">
                Subtotal
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(linea, i) in lineas"
              :key="linea.variante_id"
              :class="{ 'conv-fila--fuera': !linea.lleva }"
            >
              <td>
                <q-checkbox
                  v-model="linea.lleva"
                  dense
                  :aria-label="`Lleva ${linea.variante.sku}`"
                />
              </td>
              <td>
                <div class="conv-producto">
                  {{ linea.variante.producto?.nombre }}
                </div>
                <div class="cot-meta">
                  {{ linea.variante.presentacion }} · <span class="text-mono">{{ linea.variante.sku }}</span>
                </div>
              </td>
              <td class="text-right text-mono">
                {{ formatearCantidad(linea.cotizada) }}
              </td>
              <td class="conv-cantidad">
                <q-input
                  v-model="linea.cantidad"
                  type="number"
                  min="0"
                  :max="linea.cotizada"
                  :step="linea.variante.unidad?.fraccionable ? '0.001' : '1'"
                  :suffix="linea.variante.unidad?.abreviatura"
                  :disable="!linea.lleva"
                  :aria-label="`Cantidad de ${linea.variante.sku}`"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorLinea(i))"
                  :error-message="errorLinea(i)"
                />
              </td>
              <td class="text-right text-mono">
                {{ formatearPrecio(linea.precio_unitario) }}
              </td>
              <td class="text-right text-mono">
                {{ linea.lleva ? formatearPrecio(subtotalLinea(linea)) : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="conv-total">
        <span v-if="Number(detalle?.descuento)">Descuento proporcional: −{{ formatearPrecio(descuentoConvertir) }} ·</span>
        Total del pedido: <strong class="text-mono">{{ formatearPrecio(totalConvertir) }}</strong>
      </div>

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="convertirDialog = false"
        />
        <AppButton
          variant="primary"
          label="Crear pedido"
          icon="shopping_cart_checkout"
          :disable="!lineas.some((l) => l.lleva)"
          :loading="accionando === 'convertir'"
          @click="convertir"
        />
      </template>
    </AppDialog>

    <ImpresionCotizacion ref="impresionRef" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import CotizacionService from '@/services/CotizacionService'
import { useUserStore } from '@/stores/user-store'
import { formatearCantidad } from '@/utils/cantidad'
import { descargarArchivo } from '@/utils/descargar'
import { formatearPrecio } from '@/utils/moneda'
import CotizacionDetalle from './CotizacionDetalle.vue'
import CotizacionForm from './CotizacionForm.vue'
import ImpresionCotizacion from './ImpresionCotizacion.vue'
import { ESTADOS, fechaCorta } from './constantes'

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const columns = [
  { name: 'codigo', label: 'Cotización', field: 'codigo', align: 'left', sortable: true },
  { name: 'cliente', label: 'Cliente', field: (row) => row.cliente?.nombre, align: 'left' },
  { name: 'valida_hasta', label: 'Válida hasta', field: 'valida_hasta', align: 'left', sortable: true, format: fechaCorta, classes: 'text-mono' },
  { name: 'items_count', label: 'Ítems', field: 'items_count', align: 'right', classes: 'text-mono' },
  { name: 'total', label: 'Total', field: (row) => formatearPrecio(row.total), align: 'right', sortable: true, classes: 'text-mono' },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// ── Filtros ──
const search = ref('')
const busqueda = ref('')
const estadoFilter = ref(null)

let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const estadoOptions = [
  { label: 'Todos', value: null },
  ...Object.entries(ESTADOS).map(([value, { label }]) => ({ label, value }))
]

const hayFiltros = computed(() => Boolean(search.value || estadoFilter.value))

function limpiarFiltros () {
  search.value = ''
  estadoFilter.value = null
}

const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, estado: estadoFilter.value }))

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: 'codigo', descending: true, page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true

  try {
    const columna = sortBy === 'codigo' ? 'id' : sortBy
    const params = { rowsPerPage, page, search: busqueda.value, order_by: descending ? `-${columna}` : columna }
    if (estadoFilter.value) params.estado = estadoFilter.value

    const { data, total = 0 } = await CotizacionService.getData({ params })
    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
})

function refrescar () {
  tableRef.value.requestServerInteraction()
}

// ── Crear / editar ──
const formDialog = ref(false)
const formRef = ref()
const editId = ref(null)
const aperturas = ref(0)

function crear () {
  editId.value = null
  aperturas.value++
  formDialog.value = true
}

function editar (cotizacion) {
  detalleDialog.value = false
  editId.value = cotizacion.id
  formDialog.value = true
}

function guardada (cotizacion) {
  formDialog.value = false
  refrescar()
  $q.notify({ type: 'positive', message: `Cotización ${cotizacion?.codigo ?? ''} guardada.`, position: 'top-right', timeout: 1500 })
  if (cotizacion) ver(cotizacion)
}

const impresionRef = ref()

// ── Detalle y acciones ──
const detalleDialog = ref(false)
const detalleRef = ref()
const detalleId = ref(null)
const detalle = computed(() => detalleRef.value?.cotizacion ?? null)
const pendiente = computed(() => ['pendiente', 'vencida'].includes(detalle.value?.estado))

function ver (cotizacion) {
  detalleId.value = cotizacion.id
  detalleDialog.value = true
}

// ?ver=ID (desde la búsqueda global o la campana) abre ese detalle, también
// si ya estábamos en esta pantalla.
watch(() => route.query.ver, (id) => {
  if (!id) return
  ver({ id: Number(id) })
  router.replace({ query: { ...route.query, ver: undefined } })
}, { immediate: true })

const accionando = ref(null)

async function bajarExcel () {
  accionando.value = 'excel'
  try {
    await descargarArchivo(`api/cotizaciones/${detalle.value.id}/excel`, `${detalle.value.codigo}.xlsx`)
  } catch {
    $q.notify({ type: 'negative', message: 'No se pudo generar el Excel.', position: 'top-right' })
  } finally {
    accionando.value = null
  }
}

// ── Convertir: elegir qué lleva y cuánto ──
const convertirDialog = ref(false)
const lineas = ref([])
const erroresConvertir = ref({})

function abrirConvertir () {
  erroresConvertir.value = {}
  lineas.value = detalle.value.items.map((item) => ({
    variante_id: item.variante_id,
    variante: item.variante,
    cotizada: Number(item.cantidad),
    cantidad: String(Number(item.cantidad)),
    precio_unitario: item.precio_unitario,
    lleva: true
  }))
  convertirDialog.value = true
}

function subtotalLinea (linea) {
  const n = Number(linea.cantidad)
  return Number.isFinite(n) ? Math.round(n * Number(linea.precio_unitario) * 100) / 100 : 0
}

// Vista previa: el descuento de la cotización se reparte en proporción a lo
// que se lleva (lo mismo que hace el backend).
const subtotalConvertir = computed(() => lineas.value.filter((l) => l.lleva).reduce((s, l) => s + subtotalLinea(l), 0))
const descuentoConvertir = computed(() => {
  const original = Number(detalle.value?.subtotal ?? 0)
  if (!original) return 0
  return Math.min(subtotalConvertir.value, Math.round(Number(detalle.value.descuento) * subtotalConvertir.value / original * 100) / 100)
})
const totalConvertir = computed(() => subtotalConvertir.value - descuentoConvertir.value)

// Los errores del backend vienen por posición entre las que se llevan.
function errorLinea (i) {
  const n = lineas.value.slice(0, i).filter((l) => l.lleva).length
  return lineas.value[i].lleva ? erroresConvertir.value[`items.${n}.cantidad`]?.[0] : undefined
}

async function convertir () {
  accionando.value = 'convertir'
  erroresConvertir.value = {}
  try {
    // Todo tal cual: sin `items` (convierte la cotización entera).
    const todo = lineas.value.every((l) => l.lleva && Number(l.cantidad) === l.cotizada)
    const items = todo ? undefined : lineas.value.filter((l) => l.lleva).map((l) => ({ variante_id: l.variante_id, cantidad: l.cantidad }))
    const actualizada = await CotizacionService.convertir(detalle.value.id, items)
    convertirDialog.value = false
    detalleRef.value.actualizar(actualizada)
    refrescar()
    $q.notify({
      type: 'positive',
      message: `Se creó el pedido ${actualizada.pedido?.codigo}${todo ? '' : ' con lo que lleva el cliente'}: confirmalo desde Pedidos.`,
      position: 'top-right',
      timeout: 3500
    })
  } catch (error) {
    const { status, data } = error.response ?? {}
    if (status === 422) erroresConvertir.value = data?.errors ?? {}
    const primero = Object.values(data?.errors ?? {})[0]?.[0]
    if (status === 422 || status === 409) {
      $q.notify({ type: 'negative', message: primero ?? data?.message, position: 'top-right', timeout: 4500 })
    }
  } finally {
    accionando.value = null
  }
}

async function ejecutar (accion) {
  accionando.value = accion
  try {
    const actualizada = await CotizacionService[accion](detalle.value.id)
    detalleRef.value.actualizar(actualizada)
    refrescar()
    $q.notify({
      type: 'positive',
      message: accion === 'convertir'
        ? `Se creó el pedido ${actualizada.pedido?.codigo}: confirmalo desde Pedidos.`
        : 'Cotización marcada como rechazada.',
      position: 'top-right',
      timeout: 3500
    })
  } catch (error) {
    const { status, data } = error.response ?? {}
    if (status === 422 || status === 409) {
      const primero = Object.values(data?.errors ?? {})[0]?.[0]
      $q.notify({ type: 'negative', message: primero ?? data?.message, position: 'top-right', timeout: 4500 })
    }
  } finally {
    accionando.value = null
  }
}
</script>

<style lang="scss" scoped>
.cot-codigo {
  padding: 0;
  border: 0;
  background: none;
  font-weight: 600;
  color: $primary;
  cursor: pointer;

  &:hover {
    text-decoration: underline;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
}

.conv-ayuda {
  margin: 0 0 12px;
  font-size: 13px;
  color: var(--app-ink-2);
}

.conv-tablaWrap {
  overflow-x: auto;
}

.conv-tabla {
  width: 100%;
  min-width: 620px;
  border-collapse: collapse;

  th {
    padding: 0 6px 6px;
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    color: var(--app-ink-2);
  }

  td {
    padding: 6px;
    vertical-align: middle;
    border-top: 1px solid var(--app-border-subtle);
    font-size: 13px;
    color: var(--app-ink);
  }

  .text-right {
    text-align: right;
  }
}

.conv-fila--fuera td {
  opacity: 0.45;
}

.conv-producto {
  font-weight: 600;
}

.conv-cantidad {
  width: 140px;
}

.conv-total {
  margin-top: 12px;
  font-size: 13.5px;
  text-align: right;
  color: var(--app-ink-2);

  strong {
    font-size: 16px;
    color: var(--app-ink);
  }
}

.cot-meta {
  font-size: 12px;
  color: var(--app-ink-2);
}
</style>
