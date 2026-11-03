<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Roles"
      :subtitle="`${pagination.rowsNumber} roles registrados`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('roles.store')"
          variant="primary"
          label="Nuevo rol"
          icon="add"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por nombre"
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
      no-data-label="No hay roles que coincidan con la búsqueda."
      @request="onRequest"
    >
      <template #body-cell-id="props">
        <q-td
          :props="props"
          class="text-mono"
        >
          #{{ props.row.id }}
        </q-td>
      </template>

      <template #body-cell-permisos="props">
        <q-td
          :props="props"
          class="text-mono"
        >
          {{ contarPermisos(props.row) }}
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <q-btn
            v-if="userStore.hasPermission('roles.update')"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar ${props.row.name}`"
            @click="editar(props.row)"
          />
          <q-btn
            v-if="userStore.hasPermission('roles.destroy')"
            flat
            dense
            round
            icon="delete_outline"
            size="sm"
            color="negative"
            :aria-label="`Eliminar ${props.row.name}`"
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
      <RolesForm
        :id="editId"
        ref="formRef"
        :key="editId ?? 'nuevo'"
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
      title="Eliminar rol"
    >
      <p class="confirm-text">
        ¿Eliminar el rol <strong>{{ aEliminar?.name }}</strong>? Los usuarios que lo
        tengan asignado pierden sus permisos. Esta acción no se puede deshacer.
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
import RolService from '@/services/RolService'
import { useUserStore } from '@/stores/user-store'
import RolesForm from './RolesForm.vue'

const $q = useQuasar()
const userStore = useUserStore()

const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
  { name: 'name', label: 'Nombre', field: 'name', align: 'left', sortable: true },
  { name: 'permisos', label: 'Permisos', field: (row) => row.permissions?.length ?? 0, align: 'left' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: 'id', descending: true, page: 1, rowsPerPage: 10, rowsNumber: 0 })

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
    const { data, total = 0 } = await RolService.getData({
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

function contarPermisos (row) {
  const total = row.permissions?.length ?? 0
  return `${total} ${total === 1 ? 'permiso' : 'permisos'}`
}

// ── Crear / editar en diálogo ──
const formDialog = ref(false)
const formRef = ref()
const title = ref('')
const editId = ref(null)

function crear () {
  editId.value = null
  title.value = 'Nuevo rol'
  formDialog.value = true
}

function editar (row) {
  editId.value = row.id
  title.value = 'Editar rol'
  formDialog.value = true
}

function save () {
  formDialog.value = false
  tableRef.value.requestServerInteraction()
  $q.notify({ type: 'positive', message: 'Rol guardado.', position: 'top-right', timeout: 1500 })
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
    await RolService.delete(aEliminar.value.id)
    confirmDialog.value = false
    tableRef.value.requestServerInteraction()
    $q.notify({ type: 'positive', message: 'Rol eliminado.', position: 'top-right', timeout: 1500 })
  } finally {
    eliminando.value = false
  }
}
</script>

<style lang="scss" scoped>
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
