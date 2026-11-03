<template>
  <div class="app-list-page">
    <!-- Cada permiso es una ruta de la API. "Nuevo permiso" ofrece sólo las
         rutas que todavía no tienen el suyo: no se tipean nombres. -->
    <AppPageHeader
      title="Permisos"
      :subtitle="`${pagination.rowsNumber} permisos · uno por cada acción de la API`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('permisos.store')"
          variant="primary"
          label="Nuevo permiso"
          icon="add"
          @click="crearDialog = true"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por ruta o descripción"
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
      no-data-label="No hay permisos que coincidan con la búsqueda."
      @request="onRequest"
    >
      <template #body-cell-name="props">
        <q-td
          :props="props"
          class="text-mono"
        >
          {{ props.row.name }}
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <q-btn
            v-if="userStore.hasPermission('permisos.update')"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar ${props.row.name}`"
            @click="editar(props.row)"
          />
        </q-td>
      </template>
    </AppTable>

    <AppDialog
      v-model="formDialog"
      title="Editar descripción"
      persistent
    >
      <PermisosForm
        v-if="editId"
        :id="editId"
        ref="formRef"
        :key="editId"
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
      v-model="crearDialog"
      title="Nuevo permiso"
      size="lg"
      persistent
    >
      <PermisosCrearForm
        ref="crearRef"
        @save="creados"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="crearDialog = false"
        />
        <AppButton
          variant="primary"
          :label="crearRef?.form.permiso.rutas.length ? `Crear ${crearRef.form.permiso.rutas.length}` : 'Crear'"
          :disable="!crearRef?.form.permiso.rutas.length"
          :loading="crearRef?.form.processing"
          @click="crearRef.submit()"
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
import PermisoService from '@/services/PermisoService'
import { useUserStore } from '@/stores/user-store'
import PermisosCrearForm from './PermisosCrearForm.vue'
import PermisosForm from './PermisosForm.vue'

const $q = useQuasar()
const userStore = useUserStore()

const columns = [
  { name: 'name', label: 'Ruta', field: 'name', align: 'left', sortable: true },
  { name: 'description', label: 'Descripción', field: 'description', align: 'left', sortable: true },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
// Por nombre y no por id: así quedan agrupados por módulo (roles.*, usuarios.*).
const pagination = ref({ sortBy: 'name', descending: false, page: 1, rowsPerPage: 10, rowsNumber: 0 })

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
    const { data, total = 0 } = await PermisoService.getData({
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

// ── Crear desde rutas disponibles ──
const crearDialog = ref(false)
const crearRef = ref()

function creados (cantidad) {
  crearDialog.value = false
  tableRef.value.requestServerInteraction()
  $q.notify({
    type: 'positive',
    message: cantidad === 1 ? 'Se creó 1 permiso.' : `Se crearon ${cantidad} permisos.`,
    position: 'top-right',
    timeout: 1500
  })
}

// ── Editar descripción ──
const formDialog = ref(false)
const formRef = ref()
const editId = ref(null)

function editar (row) {
  editId.value = row.id
  formDialog.value = true
}

function save () {
  formDialog.value = false
  tableRef.value.requestServerInteraction()
  $q.notify({ type: 'positive', message: 'Descripción guardada.', position: 'top-right', timeout: 1500 })
}
</script>
