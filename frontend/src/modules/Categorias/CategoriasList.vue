<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Categorías"
      :subtitle="`${pagination.rowsNumber} categorías registradas`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('categorias.store')"
          variant="primary"
          label="Nueva categoría"
          icon="add"
          @click="crear()"
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
      no-data-label="No hay categorías que coincidan con la búsqueda."
      @request="onRequest"
    >
      <template #body-cell-padre="props">
        <q-td :props="props">
          <span v-if="props.row.padre">{{ props.row.padre.nombre }}</span>
          <span
            v-else
            class="categoria-raiz"
          >Principal</span>
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <q-btn
            v-if="userStore.hasPermission('categorias.store')"
            flat
            dense
            round
            icon="add"
            size="sm"
            color="grey-7"
            :aria-label="`Nueva subcategoría de ${props.row.nombre}`"
            @click="crear(props.row)"
          >
            <q-tooltip>Nueva subcategoría</q-tooltip>
          </q-btn>
          <q-btn
            v-if="userStore.hasPermission('categorias.update')"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar ${props.row.nombre}`"
            @click="editar(props.row)"
          />
          <!-- Con subcategorías no se puede borrar (el backend responde 409):
               se deshabilita en vez de dejar que falle. -->
          <q-btn
            v-if="userStore.hasPermission('categorias.destroy')"
            flat
            dense
            round
            icon="delete_outline"
            size="sm"
            color="negative"
            :disable="props.row.hijos_count > 0"
            :aria-label="`Eliminar ${props.row.nombre}`"
            @click="eliminar(props.row)"
          >
            <q-tooltip v-if="props.row.hijos_count > 0">
              Tiene subcategorías: elimínelas o muévalas primero
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
      <CategoriasForm
        ref="formRef"
        :key="`${editId ?? 'nueva'}-${parentId ?? ''}`"
        :id="editId"
        :parent-id="parentId"
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
      title="Eliminar categoría"
    >
      <p class="confirm-text">
        ¿Eliminar la categoría <strong>{{ aEliminar?.nombre }}</strong>? Esta acción no se puede deshacer.
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
import CategoriaService from '@/services/CategoriaService'
import { useUserStore } from '@/stores/user-store'
import CategoriasForm from './CategoriasForm.vue'

const $q = useQuasar()
const userStore = useUserStore()

const columns = [
  { name: 'nombre', label: 'Nombre', field: 'nombre', align: 'left', sortable: true },
  { name: 'padre', label: 'Categoría padre', field: (row) => row.padre?.nombre, align: 'left' },
  { name: 'hijos_count', label: 'Subcategorías', field: 'hijos_count', align: 'center' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: 'nombre', descending: false, page: 1, rowsPerPage: 10, rowsNumber: 0 })

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
    const { data, total = 0 } = await CategoriaService.getData({
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
const parentId = ref(null)

// Con `padre` crea una subcategoría ya ubicada debajo de esa fila.
function crear (padre = null) {
  editId.value = null
  parentId.value = padre?.id ?? null
  title.value = padre ? `Nueva subcategoría de ${padre.nombre}` : 'Nueva categoría'
  formDialog.value = true
}

function editar (row) {
  editId.value = row.id
  parentId.value = null
  title.value = 'Editar categoría'
  formDialog.value = true
}

function save () {
  formDialog.value = false
  tableRef.value.requestServerInteraction()
  $q.notify({ type: 'positive', message: 'Categoría guardada.', position: 'top-right', timeout: 1500 })
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
    await CategoriaService.delete(aEliminar.value.id)
    confirmDialog.value = false
    tableRef.value.requestServerInteraction()
    $q.notify({ type: 'positive', message: 'Categoría eliminada.', position: 'top-right', timeout: 1500 })
  } catch (error) {
    // 409: alguien le agregó subcategorías después de cargar la tabla.
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
.categoria-raiz {
  color: var(--app-ink-2);
  font-style: italic;
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
