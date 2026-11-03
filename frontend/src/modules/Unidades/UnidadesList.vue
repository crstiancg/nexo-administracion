<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Unidades de medida"
      :subtitle="`${pagination.rowsNumber} unidades registradas`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('unidades.store')"
          variant="primary"
          label="Nueva unidad"
          icon="add"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por nombre o abreviatura"
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
      no-data-label="No hay unidades que coincidan con la búsqueda."
      @request="onRequest"
    >
      <template #body-cell-nombre="props">
        <q-td :props="props">
          <span class="registro-nombre">{{ props.row.nombre }}</span>
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <q-btn
            v-if="userStore.hasPermission('unidades.update')"
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
            v-if="userStore.hasPermission('unidades.destroy')"
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
      :title="title"
      persistent
    >
      <UnidadesForm
        :id="editId"
        ref="formRef"
        :key="editId ?? 'nueva'"
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
      title="Eliminar unidad"
    >
      <p class="confirm-text">
        ¿Eliminar la unidad <strong>{{ aEliminar?.nombre }}</strong>? Esta acción no se puede deshacer.
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
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import UnidadService from '@/services/UnidadService'
import { useUserStore } from '@/stores/user-store'
import UnidadesForm from './UnidadesForm.vue'

const $q = useQuasar()
const userStore = useUserStore()

const columns = [
  { name: 'nombre', label: 'Nombre', field: 'nombre', align: 'left', sortable: true },
  { name: 'abreviatura', label: 'Abreviatura', field: 'abreviatura', align: 'left', sortable: true, classes: 'text-mono' },
  { name: 'fraccionable', label: 'Venta', field: 'fraccionable', align: 'left', format: (v) => v ? 'Con decimales' : 'Por unidad entera' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: 'nombre', descending: false, page: 1, rowsPerPage: 20, rowsNumber: 0 })

// El buscador escribe en `search`; a la tabla le llega `filter` recién cuando
// se deja de tipear, para no pegarle a la API por cada tecla.
const search = ref('')
const filter = ref('')
let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filter.value = value.trim() }, 400)
})

async function onRequest ({ pagination: requested, filter: term }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true

  try {
    const { data, total = 0 } = await UnidadService.getData({
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

function crear () {
  editId.value = null
  title.value = 'Nueva unidad'
  formDialog.value = true
}

function editar (row) {
  editId.value = row.id
  title.value = 'Editar unidad'
  formDialog.value = true
}

function save () {
  formDialog.value = false
  tableRef.value.requestServerInteraction()
  $q.notify({ type: 'positive', message: 'Unidad guardada.', position: 'top-right', timeout: 1500 })
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
    await UnidadService.delete(aEliminar.value.id)
    confirmDialog.value = false
    tableRef.value.requestServerInteraction()
    $q.notify({ type: 'positive', message: 'Unidad eliminada.', position: 'top-right', timeout: 1500 })
  } catch (error) {
    // 409: la unidad la usa alguna presentación de producto.
    if (error.response?.status === 409) {
      confirmDialog.value = false
      $q.notify({ type: 'negative', message: error.response.data.message, position: 'top-right', timeout: 3000 })
    }
  } finally {
    eliminando.value = false
  }
}
</script>

<style lang="scss" scoped>
.registro-nombre {
  font-weight: 600;
  color: var(--app-ink);
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
