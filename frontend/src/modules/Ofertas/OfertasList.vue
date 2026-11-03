<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Ofertas"
      :subtitle="`${pagination.rowsNumber} ofertas`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('ofertas.store')"
          variant="primary"
          label="Nueva oferta"
          icon="add"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por nombre, producto o categoría"
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
      no-data-label="No hay ofertas que coincidan."
      @request="onRequest"
    >
      <template #body-cell-nombre="props">
        <q-td :props="props">
          <div class="oferta-nombre">
            {{ props.row.nombre }}
            <span class="oferta-etiqueta">{{ props.row.etiqueta }}</span>
          </div>
        </q-td>
      </template>

      <template #body-cell-alcance="props">
        <q-td :props="props">
          <template v-if="props.row.alcance === 'productos'">
            <q-icon
              name="checkroom"
              size="14px"
            /> {{ resumenProductos(props.row.productos) }}
            <q-tooltip v-if="props.row.productos.length > 1">
              <div
                v-for="p in props.row.productos"
                :key="p.id"
              >
                {{ p.nombre }}{{ p.variantes.length ? ` (${p.variantes.length} variantes)` : '' }}
              </div>
            </q-tooltip>
          </template>
          <template v-else>
            <q-icon
              name="category"
              size="14px"
            /> {{ props.row.categorias.map((c) => c.nombre).join(', ') }}
            <span
              v-if="props.row.incluye_subcategorias"
              class="oferta-detalle"
            >(y subcategorías)</span>
          </template>
        </q-td>
      </template>

      <template #body-cell-periodo="props">
        <q-td :props="props">
          <div>{{ formatearFechaHora(props.row.inicia_at) }}</div>
          <div class="oferta-detalle">
            hasta {{ formatearFechaHora(props.row.termina_at) }}
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
            v-if="userStore.hasPermission('ofertas.update')"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar ${props.row.nombre}`"
            @click="editar(props.row)"
          />
          <q-btn
            v-if="userStore.hasPermission('ofertas.destroy')"
            flat
            dense
            round
            icon="delete_outline"
            size="sm"
            color="negative"
            :aria-label="`Eliminar ${props.row.nombre}`"
            @click="eliminar(props.row)"
          />
        </q-td>
      </template>
    </AppTable>

    <AppDialog
      v-model="formDialog"
      :title="editId ? 'Editar oferta' : 'Nueva oferta'"
      persistent
    >
      <OfertasForm
        :id="editId"
        ref="formRef"
        :key="editId ?? `nueva-${aperturas}`"
        @save="save"
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

    <AppDialog
      v-model="confirmDialog"
      title="Eliminar oferta"
    >
      <p class="confirm-text">
        ¿Eliminar <strong>{{ aEliminar?.nombre }}</strong>? Las ventas que ya se hicieron con esta oferta
        no cambian: su precio quedó guardado en cada pedido.
      </p>

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="confirmDialog = false"
        />
        <AppButton
          variant="destructive"
          label="Eliminar"
          data-test="confirmar-eliminar"
          :loading="eliminando"
          @click="confirmarEliminar"
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
import OfertaService from '@/services/OfertaService'
import { useUserStore } from '@/stores/user-store'
import { formatearFechaHora } from '@/utils/fechas'
import { ESTADOS } from './FormOferta'
import OfertasForm from './OfertasForm.vue'

const $q = useQuasar()
const userStore = useUserStore()

const columns = [
  { name: 'nombre', label: 'Oferta', field: 'nombre', align: 'left', sortable: true },
  { name: 'alcance', label: 'Se aplica a', field: 'alcance', align: 'left' },
  { name: 'periodo', label: 'Período', field: 'inicia_at', align: 'left' },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// "Polo básico (2 variantes)" con uno; "3 productos" con varios (el detalle
// va en el tooltip).
function resumenProductos (productos) {
  if (productos.length !== 1) return `${productos.length} productos`
  const [p] = productos
  return p.variantes.length ? `${p.nombre} (${p.variantes.length} variantes)` : p.nombre
}

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

// AppTable vuelve a la página 1 y pide datos cuando cambia `filter`.
const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, estado: estadoFilter.value }))

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: null, descending: false, page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true
  try {
    const params = { rowsPerPage, page, search: busqueda.value }
    if (sortBy) params.order_by = descending ? `-${sortBy}` : sortBy
    if (estadoFilter.value) params.estado = estadoFilter.value

    const { data, total = 0 } = await OfertaService.getData({ params })
    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
})

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

function editar (row) {
  editId.value = row.id
  formDialog.value = true
}

function save () {
  formDialog.value = false
  tableRef.value.requestServerInteraction()
  $q.notify({ type: 'positive', message: 'Oferta guardada.', position: 'top-right', timeout: 1500 })
}

// ── Eliminar ──
const confirmDialog = ref(false)
const aEliminar = ref(null)
const eliminando = ref(false)

function eliminar (row) {
  aEliminar.value = row
  confirmDialog.value = true
}

async function confirmarEliminar () {
  eliminando.value = true
  try {
    await OfertaService.delete(aEliminar.value.id)
    confirmDialog.value = false
    tableRef.value.requestServerInteraction()
    $q.notify({ type: 'positive', message: 'Oferta eliminada.', position: 'top-right', timeout: 1500 })
  } finally {
    eliminando.value = false
  }
}
</script>

<style lang="scss" scoped>
.oferta-nombre {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  color: var(--app-ink);
}

.oferta-etiqueta {
  padding: 1px 7px;
  border-radius: 999px;
  background: $primary;
  font-size: 11px;
  font-weight: 700;
  color: #FFFFFF;
}

.oferta-detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.confirm-text {
  margin: 0;
  font-size: 14px;
  line-height: 1.55;
  color: var(--app-ink-2);

  strong {
    color: var(--app-ink);
  }
}
</style>
