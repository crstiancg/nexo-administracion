<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Auditoría"
      :subtitle="`${pagination.rowsNumber} cambios registrados`"
    />

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por código o nombre del registro"
      :has-active-filters="hayFiltros"
      :refreshing="loading"
      @clear="limpiarFiltros"
      @refresh="tableRef.actualizar()"
    >
      <AppFilterPill
        v-model="modeloFilter"
        label="Módulo"
        :options="modeloOptions"
      />
      <AppFilterPill
        v-model="eventoFilter"
        label="Acción"
        :options="eventoOptions"
      />
      <AppFilterPill
        v-if="usuarios.length"
        v-model="usuarioFilter"
        label="Usuario"
        :options="usuarioOptions"
      />
      <q-input
        v-model="diaFilter"
        type="date"
        dense
        outlined
        clearable
        aria-label="Día"
        class="aud-dia"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="No hay cambios que coincidan."
      @request="onRequest"
    >
      <template #body-cell-fecha="props">
        <q-td :props="props">
          <div class="text-mono">
            {{ formatearFechaHora(props.row.fecha) }}
          </div>
          <div
            v-if="props.row.ip"
            class="aud-meta text-mono"
          >
            {{ props.row.ip }}
          </div>
        </q-td>
      </template>

      <template #body-cell-usuario="props">
        <q-td :props="props">
          <div
            v-if="props.row.usuario"
            class="aud-usuario"
          >
            <AppAvatar
              :nombre="props.row.usuario.name"
              :size="26"
            />
            {{ props.row.usuario.name }}
          </div>
          <span
            v-else
            class="aud-meta"
          >Sistema</span>
        </q-td>
      </template>

      <template #body-cell-registro="props">
        <q-td :props="props">
          <AppChip
            :status="EVENTOS[props.row.evento].status"
            :label="EVENTOS[props.row.evento].label"
          />
          <div class="aud-registro">
            {{ props.row.modelo_label }}
            <strong>{{ props.row.etiqueta ?? `#${props.row.modelo_id}` }}</strong>
          </div>
        </q-td>
      </template>

      <!-- Antes → después de cada campo cambiado. -->
      <template #body-cell-cambios="props">
        <q-td :props="props">
          <ul
            v-if="props.row.cambios"
            class="aud-cambios"
          >
            <li
              v-for="(valores, campo) in props.row.cambios"
              :key="campo"
            >
              <span class="aud-campo">{{ nombreCampo(campo) }}</span>
              <span class="aud-antes">{{ mostrar(valores[0]) }}</span>
              <q-icon name="arrow_forward" />
              <span class="aud-despues">{{ mostrar(valores[1]) }}</span>
            </li>
          </ul>
          <span
            v-else
            class="aud-meta"
          >—</span>
        </q-td>
      </template>
    </AppTable>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AppAvatar from '@/components/AppAvatar.vue'
import AppChip from '@/components/AppChip.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import AuditoriaService from '@/services/AuditoriaService'
import { formatearFechaHora } from '@/utils/fechas'

/**
 * Quién cambió qué y cuándo: precios, ventas, pagos, caja, usuarios… Sólo
 * lectura. Cada fila muestra el antes y el después de lo que cambió.
 */
const EVENTOS = {
  creado: { label: 'Creó', status: 'positive' },
  editado: { label: 'Cambió', status: 'info' },
  borrado: { label: 'Borró', status: 'negative' }
}

// "precio_mayor" → "Precio mayor".
function nombreCampo (campo) {
  const texto = campo.replace(/_id$/, '').replace(/_/g, ' ')
  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

function mostrar (valor) {
  if (valor === null || valor === '') return '(vacío)'
  if (valor === true) return 'Sí'
  if (valor === false) return 'No'
  return String(valor)
}

const columns = [
  { name: 'fecha', label: 'Cuándo', field: 'fecha', align: 'left' },
  { name: 'usuario', label: 'Quién', field: (row) => row.usuario?.name, align: 'left' },
  { name: 'registro', label: 'Qué', field: 'etiqueta', align: 'left' },
  { name: 'cambios', label: 'Cambios', field: 'id', align: 'left', tarjeta: 'ancho' }
]

// ── Filtros ──
const search = ref('')
const busqueda = ref('')
const modeloFilter = ref(null)
const eventoFilter = ref(null)
const usuarioFilter = ref(null)
const diaFilter = ref(null)

const modelos = ref([])
const usuarios = ref([])
const modeloOptions = computed(() => [{ label: 'Todos', value: null }, ...modelos.value])
const usuarioOptions = computed(() => [{ label: 'Todos', value: null }, ...usuarios.value.map((u) => ({ label: u.name, value: u.id }))])
const eventoOptions = [
  { label: 'Todas', value: null },
  ...Object.entries(EVENTOS).map(([value, { label }]) => ({ label, value }))
]

let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const hayFiltros = computed(() => Boolean(search.value || modeloFilter.value || eventoFilter.value || usuarioFilter.value || diaFilter.value))

function limpiarFiltros () {
  search.value = ''
  modeloFilter.value = null
  eventoFilter.value = null
  usuarioFilter.value = null
  diaFilter.value = null
}

const filtroTabla = computed(() => JSON.stringify({
  search: busqueda.value,
  modelo: modeloFilter.value,
  evento: eventoFilter.value,
  usuario: usuarioFilter.value,
  dia: diaFilter.value
}))

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: null, descending: true, page: 1, rowsPerPage: 30, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  loading.value = true
  try {
    const params = { page: requested.page, rowsPerPage: requested.rowsPerPage, search: busqueda.value }
    if (modeloFilter.value) params.modelo = modeloFilter.value
    if (eventoFilter.value) params.evento = eventoFilter.value
    if (usuarioFilter.value) params.user_id = usuarioFilter.value
    if (diaFilter.value) Object.assign(params, { desde: diaFilter.value, hasta: diaFilter.value })

    const respuesta = await AuditoriaService.getData({ params })
    rows.value = respuesta.data
    modelos.value = respuesta.modelos
    usuarios.value = respuesta.usuarios
    pagination.value = { ...requested, rowsNumber: respuesta.total }
  } finally {
    loading.value = false
  }
}

onMounted(() => tableRef.value.requestServerInteraction())
</script>

<style lang="scss" scoped>
.aud-meta {
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.aud-usuario {
  display: flex;
  align-items: center;
  gap: 8px;
}

.aud-registro {
  margin-top: 4px;
  font-size: 13px;

  strong {
    margin-left: 4px;
  }
}

.aud-dia {
  width: 170px;
}

.aud-cambios {
  display: flex;
  flex-direction: column;
  gap: 3px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 12.5px;

  li {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
  }
}

.aud-campo {
  font-weight: 600;
  color: var(--app-ink-2);
}

.aud-antes {
  color: var(--q-negative);
  text-decoration: line-through;
  overflow-wrap: anywhere;
}

.aud-despues {
  font-weight: 600;
  color: var(--q-positive);
  overflow-wrap: anywhere;
}
</style>
