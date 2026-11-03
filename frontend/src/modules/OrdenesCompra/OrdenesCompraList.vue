<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Pedidos de compra"
      :subtitle="`${pagination.rowsNumber} pedidos a proveedores`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('ordenes-compra.store')"
          variant="primary"
          label="Nuevo pedido de compra"
          icon="add_shopping_cart"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por código, proveedor o RUC"
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
      no-data-label="No hay pedidos de compra. Creá uno desde aquí o desde Por reponer."
      @request="onRequest"
    >
      <template #body-cell-codigo="props">
        <q-td :props="props">
          <button
            type="button"
            class="oc-codigo text-mono"
            @click="ver(props.row)"
          >
            {{ props.row.codigo }}
          </button>
          <div class="oc-meta">
            {{ fechaCorta(props.row.fecha) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-proveedor="props">
        <q-td :props="props">
          <div>{{ props.row.proveedor?.razon_social }}</div>
          <div class="oc-meta text-mono">
            {{ props.row.proveedor?.ruc }}
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
            v-if="props.row.compra"
            class="oc-meta text-mono"
          >
            → {{ props.row.compra.codigo }}
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
            v-if="props.row.editable && userStore.hasPermission('ordenes-compra.update')"
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
      :title="editId ? 'Editar pedido de compra' : 'Nuevo pedido de compra'"
      size="lg"
      persistent
    >
      <OrdenCompraForm
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
      title="Pedido de compra"
      size="lg"
    >
      <OrdenCompraDetalle
        v-if="detalleId"
        :id="detalleId"
        ref="detalleRef"
        :key="detalleId"
      />

      <template #actions>
        <AppButton
          v-if="detalle?.abierta && userStore.hasPermission('ordenes-compra.anular')"
          variant="destructive"
          label="Anular"
          icon="block"
          @click="anularDialog = true"
        />
        <AppButton
          v-if="detalle"
          variant="tertiary"
          label="Imprimir / PDF"
          icon="print"
          @click="impresionRef.imprimir(detalle)"
        />
        <AppButton
          v-if="detalle?.editable && userStore.hasPermission('ordenes-compra.update')"
          variant="secondary"
          label="Editar"
          icon="edit"
          @click="editar(detalle)"
        />
        <AppButton
          v-if="detalle?.estado === 'pendiente' && userStore.hasPermission('ordenes-compra.enviar')"
          variant="secondary"
          label="Marcar enviado"
          icon="send"
          @click="confirmarEnvio"
        />
        <AppButton
          v-if="detalle?.abierta && userStore.hasPermission('compras.store')"
          variant="primary"
          label="Registrar recepción"
          icon="inventory"
          @click="recibir"
        />
      </template>
    </AppDialog>

    <!-- ── Recepción: la compra nace llena con lo pedido ── -->
    <AppDialog
      v-model="compraDialog"
      :title="`Recepción de ${detalle?.codigo ?? ''}`"
      size="lg"
      persistent
    >
      <CompraForm
        v-if="compraDialog && detalle"
        ref="compraRef"
        :key="`recibir-${detalle.id}`"
        :orden="detalle"
        @save="recibida"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="compraDialog = false"
        />
        <AppButton
          variant="primary"
          label="Registrar compra"
          :loading="compraRef?.form.processing"
          @click="compraRef.submit()"
        />
      </template>
    </AppDialog>

    <!-- ── Anular ── -->
    <AppDialog
      v-model="anularDialog"
      title="Anular pedido de compra"
    >
      <p class="oc-confirm">
        <strong>{{ detalle?.codigo }}</strong> queda anulado y ya no se puede recibir. No mueve stock.
      </p>
      <AppTextField
        v-model="motivo"
        label="Motivo"
        placeholder="El proveedor no tiene stock"
        maxlength="255"
        :error="errorAnular"
        autofocus
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Volver"
          @click="anularDialog = false"
        />
        <AppButton
          variant="destructive"
          label="Anular"
          :disable="!motivo.trim()"
          :loading="accionando === 'anular'"
          @click="anular"
        />
      </template>
    </AppDialog>

    <ImpresionOrdenCompra ref="impresionRef" />
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
import AppTextField from '@/components/AppTextField.vue'
import CompraForm from '@/modules/Compras/CompraForm.vue'
import OrdenCompraService from '@/services/OrdenCompraService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import ImpresionOrdenCompra from './ImpresionOrdenCompra.vue'
import OrdenCompraDetalle from './OrdenCompraDetalle.vue'
import OrdenCompraForm from './OrdenCompraForm.vue'
import { ESTADOS, fechaCorta } from './constantes'

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const columns = [
  { name: 'codigo', label: 'Pedido', field: 'codigo', align: 'left', sortable: true },
  { name: 'proveedor', label: 'Proveedor', field: (row) => row.proveedor?.razon_social, align: 'left' },
  { name: 'fecha_entrega', label: 'Entrega', field: 'fecha_entrega', align: 'left', sortable: true, format: (v) => fechaCorta(v) || '—', classes: 'text-mono' },
  { name: 'items_count', label: 'Ítems', field: 'items_count', align: 'right', classes: 'text-mono' },
  { name: 'total', label: 'Total est.', field: (row) => (Number(row.total) ? formatearPrecio(row.total) : '—'), align: 'right', sortable: true, classes: 'text-mono' },
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

    const { data, total = 0 } = await OrdenCompraService.getData({ params })
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

function editar (orden) {
  detalleDialog.value = false
  editId.value = orden.id
  formDialog.value = true
}

function guardada (orden) {
  formDialog.value = false
  refrescar()
  $q.notify({ type: 'positive', message: `Pedido de compra ${orden?.codigo ?? ''} guardado.`, position: 'top-right', timeout: 1500 })
  if (orden) ver(orden)
}

const impresionRef = ref()

// ── Detalle y acciones ──
const detalleDialog = ref(false)
const detalleRef = ref()
const detalleId = ref(null)
const detalle = computed(() => detalleRef.value?.orden ?? null)

function ver (orden) {
  detalleId.value = orden.id
  detalleDialog.value = true
}

// ?ver=ID (al crearla desde Por reponer) abre ese detalle.
watch(() => route.query.ver, (id) => {
  if (!id) return
  ver({ id: Number(id) })
  router.replace({ query: { ...route.query, ver: undefined } })
}, { immediate: true })

const accionando = ref(null)

function avisarError (error) {
  const { status, data } = error.response ?? {}
  if (status === 422 || status === 409) {
    const primero = Object.values(data?.errors ?? {})[0]?.[0]
    $q.notify({ type: 'negative', message: primero ?? data?.message, position: 'top-right', timeout: 4500 })
  }
}

// Enviado = ya no se edita: se pregunta antes.
function confirmarEnvio () {
  $q.notify({
    message: `¿Marcar ${detalle.value.codigo} como enviado al proveedor?`,
    caption: 'Después ya no se puede editar (sí anular o recibir).',
    icon: 'help_outline',
    color: 'warning',
    textColor: 'dark',
    position: 'center',
    timeout: 0,
    multiLine: true,
    actions: [
      { label: 'Cancelar', color: 'dark' },
      { label: 'Sí, enviado', color: 'dark', handler: enviar }
    ]
  })
}

async function enviar () {
  accionando.value = 'enviar'
  try {
    detalleRef.value.actualizar(await OrdenCompraService.enviar(detalle.value.id))
    refrescar()
    $q.notify({ type: 'positive', message: 'Pedido marcado como enviado.', position: 'top-right', timeout: 2000 })
  } catch (error) {
    avisarError(error)
  } finally {
    accionando.value = null
  }
}

// ── Anular ──
const anularDialog = ref(false)
const motivo = ref('')
const errorAnular = ref('')

watch(anularDialog, (abierto) => {
  if (abierto) {
    motivo.value = ''
    errorAnular.value = ''
  }
})

async function anular () {
  accionando.value = 'anular'
  try {
    detalleRef.value.actualizar(await OrdenCompraService.anular(detalle.value.id, motivo.value.trim()))
    anularDialog.value = false
    refrescar()
    $q.notify({ type: 'positive', message: 'Pedido de compra anulado.', position: 'top-right', timeout: 2000 })
  } catch (error) {
    errorAnular.value = error.response?.data?.errors?.motivo?.[0] ?? ''
    if (!errorAnular.value) avisarError(error)
  } finally {
    accionando.value = null
  }
}

// ── Recepción ──
const compraDialog = ref(false)
const compraRef = ref()

function recibir () {
  compraDialog.value = true
}

async function recibida (compra) {
  compraDialog.value = false
  detalleRef.value.actualizar(await OrdenCompraService.get(detalle.value.id))
  refrescar()
  $q.notify({
    type: 'positive',
    message: `Compra ${compra?.codigo ?? ''} registrada: la mercadería ya está en stock y el pedido quedó recibido.`,
    position: 'top-right',
    timeout: 3500
  })
}
</script>

<style lang="scss" scoped>
.oc-codigo {
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

.oc-meta {
  font-size: 12px;
  color: var(--app-ink-2);
}

.oc-confirm {
  margin: 0 0 12px;
  font-size: 13.5px;
  line-height: 1.5;
  color: var(--app-ink);
}
</style>
