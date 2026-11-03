<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Clientes"
      :subtitle="`${pagination.rowsNumber} clientes registrados`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('clientes.store')"
          variant="primary"
          label="Nuevo cliente"
          icon="person_add"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por nombre, documento o teléfono"
      :refreshing="loading"
      @refresh="tableRef.actualizar()"
    />

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filter"
      no-data-label="No hay clientes que coincidan con la búsqueda."
      @request="onRequest"
    >
      <template #body-cell-nombre="props">
        <q-td :props="props">
          <div class="cliente-nombre">
            {{ props.row.nombre }}
            <AppChip
              v-if="props.row.mayorista"
              status="info"
              label="Mayorista"
              class="q-ml-xs"
            />
          </div>
          <div
            v-if="props.row.email"
            class="cliente-detalle"
          >
            {{ props.row.email }}
          </div>
        </q-td>
      </template>

      <template #body-cell-documento="props">
        <q-td :props="props">
          <template v-if="props.row.numero_documento">
            <span class="cliente-detalle">{{ props.row.tipo_documento }}</span>
            <span class="text-mono"> {{ props.row.numero_documento }}</span>
          </template>
          <span
            v-else
            class="cliente-detalle"
          >—</span>
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <q-btn
            v-if="userStore.hasPermission('clientes.update')"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar ${props.row.nombre}`"
            @click="editar(props.row)"
          />
          <!-- Con pedidos no se borra (historial de ventas; el backend
               responde 409). -->
          <q-btn
            v-if="userStore.hasPermission('clientes.destroy')"
            flat
            dense
            round
            icon="delete_outline"
            size="sm"
            color="negative"
            :disable="props.row.pedidos_count > 0"
            :aria-label="`Eliminar ${props.row.nombre}`"
            @click="eliminar(props.row)"
          >
            <q-tooltip v-if="props.row.pedidos_count > 0">
              Tiene pedidos: no se puede eliminar
            </q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </AppTable>

    <AppDialog
      v-model="formDialog"
      :title="title"
      persistent
    >
      <ClientesForm
        :id="editId"
        ref="formRef"
        :key="editId ?? `nuevo-${aperturas}`"
        @save="save"
        @existente="irAExistente"
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
      title="Eliminar cliente"
    >
      <p class="confirm-text">
        ¿Eliminar a <strong>{{ aEliminar?.nombre }}</strong>? Esta acción no se puede deshacer.
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
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import ClienteService from '@/services/ClienteService'
import { useUserStore } from '@/stores/user-store'
import ClientesForm from './ClientesForm.vue'

const $q = useQuasar()
const userStore = useUserStore()

const columns = [
  { name: 'nombre', label: 'Cliente', field: 'nombre', align: 'left', sortable: true },
  { name: 'documento', label: 'Documento', field: 'numero_documento', align: 'left' },
  { name: 'telefono', label: 'Teléfono', field: (row) => row.telefono ?? '—', align: 'left' },
  { name: 'pedidos_count', label: 'Pedidos', field: 'pedidos_count', align: 'right', classes: 'text-mono' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: 'nombre', descending: false, page: 1, rowsPerPage: 10, rowsNumber: 0 })

// El buscador escribe en `search`; a la tabla le llega `filter` recién cuando
// se deja de tipear, para no pegarle a la API por cada tecla.
// ?search= (desde la búsqueda global) arranca filtrado.
const route = useRoute()
const search = ref(String(route.query.search ?? ''))
const filter = ref(search.value.trim())
watch(() => route.query.search, (valor) => { if (valor !== undefined) search.value = String(valor) })
let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filter.value = value.trim() }, 400)
})

async function onRequest ({ pagination: requested, filter: term }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true

  try {
    const { data, total = 0 } = await ClienteService.getData({
      params: { rowsPerPage, page, search: term, order_by: descending ? `-${sortBy}` : sortBy }
    })

    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
})

// ── Crear / editar en diálogo ──
const formDialog = ref(false)
const formRef = ref()
const title = ref('')
const editId = ref(null)
const aperturas = ref(0)

function crear () {
  editId.value = null
  aperturas.value++
  title.value = 'Nuevo cliente'
  formDialog.value = true
}

function editar (row) {
  editId.value = row.id
  title.value = 'Editar cliente'
  formDialog.value = true
}

// El documento que se está cargando ya es de otro cliente: se abre ése.
function irAExistente (cliente) {
  editar(cliente)
}

function save () {
  formDialog.value = false
  tableRef.value.requestServerInteraction()
  $q.notify({ type: 'positive', message: 'Cliente guardado.', position: 'top-right', timeout: 1500 })
}

// ── Eliminar con confirmación ──
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
    await ClienteService.delete(aEliminar.value.id)
    confirmDialog.value = false
    tableRef.value.requestServerInteraction()
    $q.notify({ type: 'positive', message: 'Cliente eliminado.', position: 'top-right', timeout: 1500 })
  } catch (error) {
    // 409: se le registró un pedido después de cargar la tabla.
    if (error.response?.status === 409) {
      confirmDialog.value = false
      tableRef.value.requestServerInteraction()
      $q.notify({ type: 'negative', message: error.response.data.message, position: 'top-right', timeout: 3000 })
    }
  } finally {
    eliminando.value = false
  }
}
</script>

<style lang="scss" scoped>
.cliente-nombre {
  font-weight: 600;
  color: var(--app-ink);
}

.cliente-detalle {
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
