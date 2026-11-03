<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Compras"
      :subtitle="`${pagination.rowsNumber} compras`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('compras.store')"
          variant="primary"
          label="Registrar compra"
          icon="add_shopping_cart"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por código, documento, proveedor o RUC"
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
      no-data-label="No hay compras que coincidan."
      @request="onRequest"
    >
      <template #body-cell-codigo="props">
        <q-td :props="props">
          <button
            type="button"
            class="compra-codigo text-mono"
            @click="ver(props.row)"
          >
            {{ props.row.codigo }}
          </button>
          <div class="compra-detalle">
            {{ fechaCorta(props.row.fecha) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-documento="props">
        <q-td :props="props">
          {{ props.row.tipo_documento_label }}
          <span class="text-mono">{{ props.row.numero_documento }}</span>
        </q-td>
      </template>

      <template #body-cell-proveedor="props">
        <q-td :props="props">
          <div>{{ props.row.proveedor?.razon_social }}</div>
          <div class="compra-detalle text-mono">
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
        </q-td>
      </template>
    </AppTable>

    <!-- ── Registrar ── -->
    <AppDialog
      v-model="formDialog"
      title="Registrar compra"
      size="lg"
      persistent
    >
      <CompraForm
        ref="formRef"
        :key="`nueva-${aperturas}`"
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
          label="Registrar"
          :loading="formRef?.form.processing"
          @click="formRef.submit()"
        />
      </template>
    </AppDialog>

    <!-- ── Detalle ── -->
    <AppDialog
      v-model="detalleDialog"
      title="Compra"
    >
      <CompraDetalle
        v-if="detalleId"
        :id="detalleId"
        ref="detalleRef"
        :key="detalleId"
      />

      <template #actions>
        <AppButton
          v-if="detalle?.estado === 'registrada' && userStore.hasPermission('compras.anular')"
          variant="destructive"
          label="Anular compra"
          icon="block"
          @click="anularDialog = true"
        />
      </template>
    </AppDialog>

    <!-- ── Anular: saca la mercadería, se pide el motivo ── -->
    <AppDialog
      v-model="anularDialog"
      title="Anular compra"
    >
      <p class="confirm-text">
        Al anular <strong>{{ detalle?.codigo }}</strong> su mercadería sale del inventario. Si ya se vendió o se trasladó
        algo, no se puede anular.
      </p>
      <AppTextField
        v-model="motivo"
        label="Motivo"
        placeholder="Se cargó con un costo equivocado"
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
          :loading="anulando"
          @click="anular"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import AppTextField from '@/components/AppTextField.vue'
import CompraService from '@/services/CompraService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import CompraDetalle from './CompraDetalle.vue'
import CompraForm from './CompraForm.vue'
import { ESTADOS, fechaCorta } from './constantes'

const $q = useQuasar()
const userStore = useUserStore()

const columns = [
  { name: 'codigo', label: 'Compra', field: 'codigo', align: 'left', sortable: true },
  { name: 'documento', label: 'Documento', field: 'numero_documento', align: 'left' },
  { name: 'proveedor', label: 'Proveedor', field: (row) => row.proveedor?.razon_social, align: 'left' },
  { name: 'sede', label: 'Sede', field: (row) => row.sede?.nombre ?? '—', align: 'left' },
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
// La más nueva primero: "codigo" ordena por id.
const pagination = ref({ sortBy: 'codigo', descending: true, page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true

  try {
    const columna = sortBy === 'codigo' ? 'id' : sortBy
    const params = { rowsPerPage, page, search: busqueda.value, order_by: descending ? `-${columna}` : columna }
    if (estadoFilter.value) params.estado = estadoFilter.value

    const { data, total = 0 } = await CompraService.getData({ params })
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

// ── Registrar ──
const formDialog = ref(false)
const formRef = ref()
const aperturas = ref(0)

function crear () {
  aperturas.value++
  formDialog.value = true
}

function guardada (compra) {
  formDialog.value = false
  refrescar()
  $q.notify({ type: 'positive', message: `Compra ${compra?.codigo ?? ''} registrada: la mercadería ya está en stock.`, position: 'top-right', timeout: 2500 })
  if (compra) ver(compra)
}

// ── Detalle ──
const detalleDialog = ref(false)
const detalleRef = ref()
const detalleId = ref(null)
const detalle = computed(() => detalleRef.value?.compra ?? null)

function ver (compra) {
  detalleId.value = compra.id
  detalleDialog.value = true
}

// ── Anular ──
const anularDialog = ref(false)
const motivo = ref('')
const anulando = ref(false)
const errorAnular = ref('')

watch(anularDialog, (abierto) => {
  if (abierto) {
    motivo.value = ''
    errorAnular.value = ''
  }
})

async function anular () {
  anulando.value = true
  errorAnular.value = ''
  try {
    detalleRef.value.actualizar(await CompraService.anular(detalle.value.id, motivo.value.trim()))
    anularDialog.value = false
    refrescar()
    $q.notify({ type: 'positive', message: 'Compra anulada: la mercadería salió del inventario.', position: 'top-right', timeout: 2500 })
  } catch (error) {
    const { status, data } = error.response ?? {}
    if (status === 422 || status === 409) {
      errorAnular.value = Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message
    }
  } finally {
    anulando.value = false
  }
}
</script>

<style lang="scss" scoped>
.compra-codigo {
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

.compra-detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.confirm-text {
  margin: 0 0 12px;
  font-size: 14px;
  line-height: 1.55;
  color: var(--app-ink-2);

  strong {
    color: var(--app-ink);
  }
}
</style>
