<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Por reponer"
      :subtitle="`${pagination.rowsNumber} presentaciones por debajo de su stock mínimo`"
    >
      <template #actions>
        <!-- Lo marcado pasa a un pedido de compra para el proveedor. -->
        <AppButton
          v-if="userStore.hasPermission('ordenes-compra.store')"
          variant="primary"
          :label="seleccion.length ? `Crear pedido de compra (${seleccion.length})` : 'Crear pedido de compra'"
          icon="add_shopping_cart"
          :disable="!seleccion.length"
          @click="ordenDialog = true"
        >
          <q-tooltip v-if="!seleccion.length">
            Marcá en la tabla lo que querés pedir
          </q-tooltip>
        </AppButton>
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por producto o SKU"
      :has-active-filters="hayFiltros"
      :refreshing="loading"
      @clear="limpiarFiltros"
      @refresh="tableRef.actualizar()"
    >
      <AppFilterPill
        v-if="sedes.length > 1"
        v-model="sedeFilter"
        label="Sede"
        :options="sedeOptions"
      />
      <AppFilterPill
        v-if="marcas.length"
        v-model="marcaFilter"
        label="Marca"
        :options="marcaOptions"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      v-model:selected="seleccion"
      :selection="userStore.hasPermission('ordenes-compra.store') ? 'multiple' : 'none'"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="Nada por reponer: todo está sobre su stock mínimo."
      @request="onRequest"
      @refresh="cargarCatalogos"
    >
      <template #body-cell-producto="props">
        <q-td :props="props">
          <div class="reponer-producto">
            {{ props.row.producto?.nombre }}
          </div>
          <div class="reponer-detalle">
            {{ props.row.presentacion }}<template v-if="props.row.color">
              · {{ props.row.color.nombre }}
            </template> ·
            <span class="text-mono">{{ props.row.sku }}</span>
            <template v-if="props.row.marca">
              · {{ props.row.marca.nombre }}
            </template>
          </div>
        </q-td>
      </template>

      <template #body-cell-stock="props">
        <q-td
          :props="props"
          class="text-right text-mono"
        >
          <span :class="props.row.stock <= 0 ? 'reponer-agotado' : 'reponer-bajo'">
            {{ formatearCantidad(props.row.stock) }}
          </span>
          {{ props.row.unidad?.abreviatura }}
        </q-td>
      </template>
    </AppTable>

    <p class="reponer-ayuda">
      El mínimo se define por presentación en el formulario del producto. Reponé con una compra o con un traslado desde otra sede
      (la columna "En la empresa" muestra si hay stock en otras sedes). Para pedirle al proveedor, filtrá por su marca, marcá lo
      que falta y tocá "Crear pedido de compra".
    </p>

    <AppDialog
      v-model="ordenDialog"
      title="Nuevo pedido de compra"
      size="lg"
      persistent
    >
      <OrdenCompraForm
        v-if="ordenDialog"
        ref="ordenRef"
        :iniciales="seleccion"
        @save="ordenGuardada"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="ordenDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar pedido"
          :loading="ordenRef?.form.processing"
          @click="ordenRef.submit()"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useRouter } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import OrdenCompraForm from '@/modules/OrdenesCompra/OrdenCompraForm.vue'
import InventarioService from '@/services/InventarioService'
import MarcaService from '@/services/MarcaService'
import SedeService from '@/services/SedeService'
import { useUserStore } from '@/stores/user-store'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'

const $q = useQuasar()
const router = useRouter()
const userStore = useUserStore()

// Lo que hay que traer para llegar al mínimo.
function faltante (row) {
  return Math.max(0, Math.round((row.stock_minimo - row.stock) * 1000) / 1000)
}

const columns = [
  { name: 'producto', label: 'Producto', field: (row) => row.producto?.nombre, align: 'left' },
  { name: 'stock', label: 'Stock aquí', field: 'stock', align: 'right' },
  { name: 'stock_minimo', label: 'Mínimo', field: 'stock_minimo', align: 'right', classes: 'text-mono', format: formatearCantidad },
  { name: 'faltante', label: 'Faltan', field: faltante, align: 'right', classes: 'text-mono reponer-faltan', format: formatearCantidad },
  { name: 'stock_total', label: 'En la empresa', field: 'stock_total', align: 'right', classes: 'text-mono', format: formatearCantidad },
  { name: 'costo', label: 'Costo prom.', field: (row) => row.costo_promedio === null ? '—' : formatearPrecio(row.costo_promedio), align: 'right', classes: 'text-mono' }
]

// ── Filtros ──
const search = ref('')
const busqueda = ref('')
let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const sedeFilter = ref(userStore.sedeId)
const sedes = ref([])
const sedeOptions = computed(() => sedes.value.map((s) => ({ label: s.nombre, value: s.id })))

const marcaFilter = ref(null)
const marcas = ref([])
const marcaOptions = computed(() => [
  { label: 'Todas', value: null },
  ...marcas.value.map((m) => ({ label: m.nombre, value: m.id }))
])

const hayFiltros = computed(() => Boolean(search.value || marcaFilter.value !== null || sedeFilter.value !== userStore.sedeId))

function limpiarFiltros () {
  search.value = ''
  marcaFilter.value = null
  sedeFilter.value = userStore.sedeId
}

const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, sede: sedeFilter.value, marca: marcaFilter.value }))

// ── Tabla ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage } = requested
  loading.value = true

  try {
    const params = { page, rowsPerPage, search: busqueda.value, bajo_minimo: 1 }
    if (sedeFilter.value) params.sede_id = sedeFilter.value
    if (marcaFilter.value) params.marca_id = marcaFilter.value

    const { data, total = 0 } = await InventarioService.variantes({ params })
    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

// Opciones de los filtros de sede y marca; también con el botón ⟳ de la tabla.
async function cargarCatalogos () {
  const [s, m] = await Promise.all([
    userStore.hasPermission('sedes.index') ? SedeService.activas() : [],
    MarcaService.getData({ params: { rowsPerPage: 0, order_by: 'nombre' } }).catch(() => ({ data: [] }))
  ])
  sedes.value = s
  marcas.value = m.data.filter((marca) => marca.activo !== false)
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
  cargarCatalogos()
})

// ── Orden de compra con lo marcado ──
// Lo marcado se queda aunque se cambie de página o de filtro: se puede
// juntar todo lo de una marca y pedirlo en una sola orden.
const seleccion = ref([])
const ordenDialog = ref(false)
const ordenRef = ref()

function ordenGuardada (orden) {
  ordenDialog.value = false
  seleccion.value = []
  $q.notify({ type: 'positive', message: `Pedido de compra ${orden?.codigo ?? ''} creado: imprimilo y mandáselo al proveedor.`, position: 'top-right', timeout: 3000 })
  if (orden) router.push({ path: '/ordenes-compra', query: { ver: orden.id } })
}
</script>

<style lang="scss" scoped>
.reponer-producto {
  font-weight: 600;
  color: var(--app-ink);
}

.reponer-detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.reponer-agotado {
  font-weight: 700;
  color: var(--q-negative);
}

.reponer-bajo {
  font-weight: 700;
  color: var(--q-warning);
}

:deep(.reponer-faltan) {
  font-weight: 700;
}

.reponer-ayuda {
  margin: 12px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--app-ink-2);
}
</style>
