<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Vencimientos"
      :subtitle="`${pagination.rowsNumber} lotes con stock · el que vence primero arriba`"
    />

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por producto, SKU o lote"
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
      <AppFilterPill
        v-if="sedes.length > 1"
        v-model="sedeFilter"
        label="Sede"
        :options="sedeOptions"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="No hay lotes que coincidan."
      @request="onRequest"
      @refresh="cargarSedes"
    >
      <template #body-cell-producto="props">
        <q-td :props="props">
          <div class="lote-producto">
            {{ props.row.variante.producto?.nombre }}
          </div>
          <div class="lote-detalle">
            {{ props.row.variante.presentacion }}<template v-if="props.row.variante.color">
              · {{ props.row.variante.color.nombre }}
            </template> ·
            <span class="text-mono">{{ props.row.variante.sku }}</span>
          </div>
        </q-td>
      </template>

      <template #body-cell-vence_at="props">
        <q-td :props="props">
          <div class="lote-vence">
            <span class="text-mono">{{ props.row.vence_at ? fecha(props.row.vence_at) : 'Sin fecha' }}</span>
            <AppChip
              v-if="ESTADOS[props.row.estado]"
              :status="ESTADOS[props.row.estado].status"
              :label="etiqueta(props.row)"
            />
          </div>
        </q-td>
      </template>
    </AppTable>

    <p class="lote-ayuda">
      Las ventas y los traslados no usan lotes vencidos. Para retirarlos, registrá una salida (merma) en Inventario eligiendo el lote.
    </p>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AppChip from '@/components/AppChip.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import InventarioService from '@/services/InventarioService'
import SedeService from '@/services/SedeService'
import { useUserStore } from '@/stores/user-store'
import { formatearCantidad } from '@/utils/cantidad'

const userStore = useUserStore()

// Mismas claves que Lote::estado() del backend.
const ESTADOS = {
  vencido: { status: 'negative' },
  por_vencer: { status: 'warning' },
  vigente: { status: 'positive' }
}

function etiqueta ({ estado, dias }) {
  if (estado === 'vencido') return dias === -1 ? 'Venció ayer' : `Vencido hace ${-dias} días`
  if (dias === 0) return 'Vence hoy'
  if (estado === 'por_vencer') return dias === 1 ? 'Vence mañana' : `Vence en ${dias} días`
  return 'Vigente'
}

function fecha (iso) {
  return iso.split('-').reverse().join('/')
}

const columns = [
  { name: 'producto', label: 'Producto', field: (row) => row.variante.producto?.nombre, align: 'left' },
  { name: 'codigo', label: 'Lote', field: 'codigo', align: 'left', classes: 'text-mono' },
  { name: 'vence_at', label: 'Vencimiento', field: 'vence_at', align: 'left' },
  { name: 'cantidad', label: 'Cantidad', field: (row) => `${formatearCantidad(row.cantidad)} ${row.variante.unidad ?? ''}`, align: 'right', classes: 'text-mono' },
  { name: 'sede', label: 'Sede', field: (row) => row.sede?.nombre, align: 'left' }
]

// ── Filtros ──
const search = ref('')
const busqueda = ref('')
let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const estadoFilter = ref(null)
const estadoOptions = [
  { label: 'Todos', value: null },
  { label: 'Vencidos', value: 'vencido' },
  { label: 'Por vencer (30 días)', value: 'por_vencer' }
]

// Arranca en la sede del usuario; 0 = todas.
const sedeFilter = ref(userStore.sedeId)
const sedes = ref([])
const sedeOptions = computed(() => [
  { label: 'Todas', value: 0 },
  ...sedes.value.map((s) => ({ label: s.nombre, value: s.id }))
])

const hayFiltros = computed(() => Boolean(search.value || estadoFilter.value || sedeFilter.value !== userStore.sedeId))

function limpiarFiltros () {
  search.value = ''
  estadoFilter.value = null
  sedeFilter.value = userStore.sedeId
}

const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, estado: estadoFilter.value, sede: sedeFilter.value }))

// ── Tabla (paginación en el servidor; el orden lo fija la API) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage } = requested
  loading.value = true

  try {
    const params = { page, rowsPerPage, search: busqueda.value, sede_id: sedeFilter.value ?? 0 }
    if (estadoFilter.value) params.estado = estadoFilter.value

    const { data, total = 0 } = await InventarioService.lotes({ params })
    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

// Opciones del filtro de sede; también con el botón ⟳ de la tabla.
async function cargarSedes () {
  if (userStore.hasPermission('sedes.index')) {
    sedes.value = await SedeService.activas()
  }
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
  cargarSedes()
})
</script>

<style lang="scss" scoped>
.lote-producto {
  font-weight: 600;
  color: var(--app-ink);
}

.lote-detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.lote-vence {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.lote-ayuda {
  margin: 12px 0 0;
  font-size: 12px;
  color: var(--app-ink-2);
}
</style>
