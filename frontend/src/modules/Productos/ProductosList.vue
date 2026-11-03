<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Productos"
      :subtitle="`${pagination.rowsNumber} productos en catálogo`"
    >
      <template #actions>
        <!-- Lo que se ve con los filtros de ahora, una fila por presentación. -->
        <AppButton
          variant="secondary"
          label="Exportar Excel"
          icon="mdi-microsoft-excel"
          :loading="exportando"
          @click="exportar"
        />
        <AppButton
          v-if="userStore.hasPermission('productos.store')"
          variant="primary"
          label="Nuevo producto"
          icon="add"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por nombre o SKU"
      :has-active-filters="hayFiltros"
      :refreshing="loading"
      @clear="limpiarFiltros"
      @refresh="tableRef.actualizar()"
    >
      <AppFilterPill
        v-model="categoriaFilter"
        label="Categoría"
        :options="categoriaOptions"
      />
      <AppFilterPill
        v-if="sedes.length > 1"
        v-model="sedeFilter"
        label="Sede"
        :options="sedeOptions"
      />
      <AppFilterPill
        v-model="marcaFilter"
        label="Marca"
        :options="marcaOptions"
      />
      <AppFilterPill
        v-model="activoFilter"
        label="Estado"
        :options="activoOptions"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="Ningún producto coincide con los filtros aplicados."
      @request="onRequest"
      @refresh="cargarCatalogos"
    >
      <template #body-cell-nombre="props">
        <q-td :props="props">
          <!-- Toda la celda lleva a la ficha (stock, lotes, movimientos). -->
          <router-link
            :to="`/productos/${props.row.id}`"
            class="producto-celda"
          >
            <!-- Miniatura WebP (unos KB), no el original. -->
            <img
              v-if="props.row.portada"
              :src="props.row.portada.miniatura_url"
              alt=""
              width="40"
              height="40"
              class="producto-portada"
              loading="lazy"
              decoding="async"
            >
            <span
              v-else
              class="producto-portada producto-portada--vacia"
            >
              <q-icon
                name="image"
                size="18px"
              />
            </span>
            <div>
              <div class="producto-nombre">
                {{ props.row.nombre }}
              </div>
              <div class="producto-variantes">
                {{ props.row.variantes_count }} {{ props.row.variantes_count === 1 ? 'variante' : 'variantes' }}
              </div>
            </div>
          </router-link>
        </q-td>
      </template>

      <template #body-cell-precio="props">
        <q-td
          :props="props"
          class="text-right text-mono"
        >
          {{ formatearPrecio(props.row.precio) }}
        </q-td>
      </template>

      <template #body-cell-stock="props">
        <q-td
          :props="props"
          class="text-right text-mono"
        >
          {{ props.row.stock_total ?? 0 }}
        </q-td>
      </template>

      <template #body-cell-activo="props">
        <q-td :props="props">
          <AppChip
            :status="props.row.activo ? 'positive' : 'negative'"
            :label="props.row.activo ? 'Activo' : 'Inactivo'"
          />
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <q-btn
            v-if="userStore.hasPermission('etiquetas.imprimir')"
            flat
            dense
            round
            icon="mdi-barcode"
            size="sm"
            color="grey-7"
            :to="{ path: '/etiquetas', query: { producto: props.row.id } }"
            :aria-label="`Imprimir etiquetas de ${props.row.nombre}`"
          >
            <q-tooltip>Imprimir etiquetas</q-tooltip>
          </q-btn>
          <q-btn
            v-if="userStore.hasPermission('productos.update')"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar ${props.row.nombre}`"
            @click="editar(props.row)"
          />
          <!-- Con stock no se borra: se desactiva (el backend responde 409). -->
          <q-btn
            v-if="userStore.hasPermission('productos.destroy')"
            flat
            dense
            round
            icon="delete_outline"
            size="sm"
            color="negative"
            :disable="Number(props.row.stock_total ?? 0) !== 0"
            :aria-label="`Eliminar ${props.row.nombre}`"
            @click="eliminar(props.row)"
          >
            <q-tooltip v-if="Number(props.row.stock_total ?? 0) !== 0">
              Tiene stock: desactívelo en su lugar
            </q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </AppTable>

    <AppDialog
      v-model="formDialog"
      :title="title"
      size="lg"
      persistent
    >
      <ProductosForm
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
      title="Eliminar producto"
    >
      <p class="confirm-text">
        ¿Eliminar el producto <strong>{{ aEliminar?.nombre }}</strong> y sus
        {{ aEliminar?.variantes_count }} variantes? Esta acción no se puede deshacer.
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
import { useRouter } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import { descargarArchivo } from '@/utils/descargar'
import CategoriaService from '@/services/CategoriaService'
import MarcaService from '@/services/MarcaService'
import SedeService from '@/services/SedeService'
import ProductoService from '@/services/ProductoService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import { opcionesPadre } from '@/modules/Categorias/arbol'
import ProductosForm from './ProductosForm.vue'

const $q = useQuasar()
const router = useRouter()
const userStore = useUserStore()

const columns = [
  { name: 'nombre', label: 'Producto', field: 'nombre', align: 'left', sortable: true },
  { name: 'marca', label: 'Marca', field: (row) => row.marca?.nombre, align: 'left' },
  { name: 'categoria', label: 'Categoría', field: (row) => row.categoria?.nombre, align: 'left' },
  { name: 'precio', label: 'Precio base', field: 'precio', align: 'right', sortable: true },
  { name: 'stock', label: 'Stock', field: 'stock_total', align: 'right' },
  { name: 'activo', label: 'Estado', field: 'activo', align: 'left' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// ── Filtros ──
const search = ref('')
const busqueda = ref('')
const categoriaFilter = ref(null)
const marcaFilter = ref(null)
// Arranca en la sede del usuario (lo que vende y su stock ahí); 0 = todo el
// catálogo con el stock de la empresa.
const sedeFilter = ref(userStore.sedeId ?? 0)
const sedes = ref([])
const sedeOptions = computed(() => [
  { label: 'Todas las sedes', value: 0 },
  ...sedes.value.map((s) => ({ label: s.nombre, value: s.id }))
])
const activoFilter = ref(null)

// El buscador espera a que se deje de tipear para no pegarle a la API por tecla.
let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const categorias = ref([])
// Filtrar por una categoría trae también sus subcategorías (lo resuelve la API).
const categoriaOptions = computed(() => [
  { label: 'Todas', value: null },
  ...opcionesPadre(categorias.value)
])

const marcas = ref([])
const marcaOptions = computed(() => [
  { label: 'Todas', value: null },
  ...marcas.value.map((m) => ({ label: m.nombre, value: m.id }))
])

const activoOptions = [
  { label: 'Todos', value: null },
  { label: 'Activos', value: 1 },
  { label: 'Inactivos', value: 0 }
]

const hayFiltros = computed(() => Boolean(search.value || categoriaFilter.value !== null || marcaFilter.value !== null || activoFilter.value !== null || sedeFilter.value !== (userStore.sedeId ?? 0)))

function limpiarFiltros () {
  search.value = ''
  categoriaFilter.value = null
  marcaFilter.value = null
  activoFilter.value = null
  sedeFilter.value = userStore.sedeId ?? 0
}

// AppTable vuelve a la página 1 y pide datos cada vez que cambia `filter`:
// se le pasa todo junto para que cualquier filtro dispare la recarga.
const filtroTabla = computed(() => JSON.stringify({
  search: busqueda.value,
  categoria_id: categoriaFilter.value,
  marca_id: marcaFilter.value,
  sede_id: sedeFilter.value,
  activo: activoFilter.value
}))

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: 'id', descending: true, page: 1, rowsPerPage: 10, rowsNumber: 0 })

// Los filtros de la pantalla, iguales para la tabla y el Excel.
function filtrosActuales () {
  const params = { search: busqueda.value, sede_id: sedeFilter.value }
  if (categoriaFilter.value !== null) params.categoria_id = categoriaFilter.value
  if (marcaFilter.value !== null) params.marca_id = marcaFilter.value
  if (activoFilter.value !== null) params.activo = activoFilter.value
  return params
}

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true

  try {
    const params = { rowsPerPage, page, order_by: descending ? `-${sortBy}` : sortBy, ...filtrosActuales() }

    const { data, total = 0 } = await ProductoService.getData({ params })

    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

// Opciones de los filtros; también con el botón ⟳ de la tabla.
async function cargarCatalogos () {
  const [c, m, s] = await Promise.all([
    CategoriaService.getData({ params: { rowsPerPage: 0 } }),
    MarcaService.getData({ params: { rowsPerPage: 0, order_by: 'nombre' } }),
    SedeService.activas()
  ])
  categorias.value = c.data
  marcas.value = m.data
  sedes.value = s
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
  cargarCatalogos()
})

// ── Excel ──
const exportando = ref(false)

async function exportar () {
  exportando.value = true
  try {
    const hoy = new Date().toLocaleDateString('en-CA')
    await descargarArchivo('api/productos/exportar', `productos-${hoy}.xlsx`, filtrosActuales())
  } catch {
    $q.notify({ type: 'negative', message: 'No se pudo generar el Excel.', position: 'top-right' })
  } finally {
    exportando.value = false
  }
}

// ── Crear / editar en diálogo ──
const formDialog = ref(false)
const formRef = ref()
const title = ref('')
const editId = ref(null)

function crear () {
  editId.value = null
  title.value = 'Nuevo producto'
  formDialog.value = true
}

function editar (row) {
  editId.value = row.id
  title.value = `Editar ${row.nombre}`
  formDialog.value = true
}

// Recién creado se va a su ficha; si no le cargaron stock inicial, con la
// entrada abierta para cargarlo (y el lote, si maneja lotes).
function save (producto) {
  formDialog.value = false
  $q.notify({ type: 'positive', message: 'Producto guardado.', position: 'top-right', timeout: 1500 })
  if (!editId.value && producto?.id) {
    // Si ya entró con stock inicial (y su lote) no hace falta la entrada.
    const conStock = producto.variantes?.some((v) => Number(v.stock) > 0)
    router.push({ path: `/productos/${producto.id}`, query: conStock ? {} : { entrada: 1 } })
    return
  }
  tableRef.value.requestServerInteraction()
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
    await ProductoService.delete(aEliminar.value.id)
    confirmDialog.value = false
    tableRef.value.requestServerInteraction()
    $q.notify({ type: 'positive', message: 'Producto eliminado.', position: 'top-right', timeout: 1500 })
  } catch (error) {
    // 409: entró stock después de cargar la tabla.
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
.producto-celda {
  display: flex;
  align-items: center;
  gap: 10px;
  color: inherit;
  text-decoration: none;

  &:hover .producto-nombre {
    color: $primary;
    text-decoration: underline;
  }
}

.producto-portada {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 8px;
  object-fit: cover;

  &--vacia {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--app-ink-2);
  }
}

.producto-nombre {
  font-weight: 600;
  color: var(--app-ink);
}

.producto-variantes {
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
