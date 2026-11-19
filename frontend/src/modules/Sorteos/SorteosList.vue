<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Sorteos"
      :subtitle="`${pagination.rowsNumber} sorteos · cada compra de un cliente registrado le da tickets`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('sorteos.store')"
          variant="primary"
          label="Nuevo sorteo"
          icon="add"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por nombre"
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
      no-data-label="Todavía no hay sorteos. Crea el primero con “Nuevo sorteo”."
      @request="onRequest"
    >
      <template #body-cell-nombre="props">
        <q-td :props="props">
          <router-link
            :to="`/sorteos/${props.row.id}`"
            class="sorteo-nombre"
          >
            {{ props.row.nombre }}
          </router-link>
          <div class="sorteo-detalle">
            {{ props.row.premios_count === 1 ? '1 premio' : `${props.row.premios_count} premios` }}
            <template v-if="props.row.sedes?.length">
              · {{ props.row.sedes.map((s) => s.nombre).join(', ') }}
            </template>
          </div>
        </q-td>
      </template>

      <template #body-cell-periodo="props">
        <q-td :props="props">
          <div>{{ fechaCorta(props.row.inicia_el) }} al {{ fechaCorta(props.row.termina_el) }}</div>
          <div class="sorteo-detalle">
            {{ PERIODOS.find((p) => p.value === props.row.periodo)?.label }} · {{ reglaDe(props.row) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-tickets="props">
        <q-td
          :props="props"
          class="text-mono"
        >
          {{ props.row.tickets_count }}
          <span class="sorteo-detalle">/ {{ props.row.participantes_count }} clientes</span>
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
            :aria-label="`Ver ${props.row.nombre}`"
            :to="`/sorteos/${props.row.id}`"
          />
          <q-btn
            v-if="props.row.editable && userStore.hasPermission('sorteos.update')"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar ${props.row.nombre}`"
            @click="editar(props.row)"
          />
        </q-td>
      </template>
    </AppTable>

    <AppDialog
      v-model="formDialog"
      :title="editId ? 'Editar sorteo' : 'Nuevo sorteo'"
      size="lg"
      persistent
    >
      <SorteosForm
        :id="editId"
        ref="formRef"
        :key="editId ?? `nuevo-${aperturas}`"
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
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useRouter } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import SorteoService from '@/services/SorteoService'
import { useUserStore } from '@/stores/user-store'
import { ESTADOS, fechaCorta, PERIODOS, reglaDe } from './constantes'
import SorteosForm from './SorteosForm.vue'

const $q = useQuasar()
const router = useRouter()
const userStore = useUserStore()

const columns = [
  { name: 'nombre', label: 'Sorteo', field: 'nombre', align: 'left', sortable: true },
  { name: 'periodo', label: 'Período y regla', field: 'inicia_el', align: 'left', sortable: true },
  { name: 'tickets', label: 'Tickets', field: 'tickets_count', align: 'left' },
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
  ...['programado', 'abierto', 'por_sortear', 'sorteado', 'cancelado'].map((value) => ({ label: ESTADOS[value].label, value }))
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
const pagination = ref({ sortBy: null, descending: false, page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true
  try {
    const params = { rowsPerPage, page, search: busqueda.value }
    if (sortBy) params.order_by = descending ? `-${sortBy}` : sortBy
    if (estadoFilter.value) params.estado = estadoFilter.value

    const { data, total = 0 } = await SorteoService.getData({ params })
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

function save (sorteo) {
  const nuevo = !editId.value
  formDialog.value = false
  $q.notify({ type: 'positive', message: 'Sorteo guardado.', position: 'top-right', timeout: 1500 })
  if (nuevo && sorteo?.id) {
    router.push(`/sorteos/${sorteo.id}`)
    return
  }
  tableRef.value.requestServerInteraction()
}
</script>

<style lang="scss" scoped>
.sorteo-nombre {
  font-weight: 600;
  color: var(--app-ink);
  text-decoration: none;

  &:hover {
    text-decoration: underline;
  }
}

.sorteo-detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}
</style>
