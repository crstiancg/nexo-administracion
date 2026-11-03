<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Ingresos y gastos"
      subtitle="Lo que entra y sale del negocio aparte de las ventas: alquiler, sueldos, servicios, repuestos…"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('conceptos-financieros.update') || userStore.hasPermission('conceptos-financieros.store')"
          variant="tertiary"
          label="Conceptos"
          icon="sell"
          @click="conceptosDialog = true"
        />
        <AppButton
          v-if="userStore.hasPermission('movimientos-financieros.store')"
          variant="secondary"
          label="Registrar ingreso"
          icon="south_west"
          @click="nuevo('ingreso')"
        />
        <AppButton
          v-if="userStore.hasPermission('movimientos-financieros.store')"
          variant="primary"
          label="Registrar gasto"
          icon="north_east"
          @click="nuevo('gasto')"
        />
      </template>
    </AppPageHeader>

    <!-- Totales del filtro -->
    <div class="mov-totales">
      <div class="mov-total mov-total--gasto">
        <span class="mov-total__label">Gastos de {{ etiquetaPeriodo }}</span>
        <strong class="text-mono">{{ formatearPrecio(totales.gastos) }}</strong>
      </div>
      <div class="mov-total mov-total--ingreso">
        <span class="mov-total__label">Otros ingresos de {{ etiquetaPeriodo }}</span>
        <strong class="text-mono">{{ formatearPrecio(totales.ingresos) }}</strong>
      </div>
      <router-link
        v-if="userStore.hasPermission('ganancias.index')"
        to="/ganancias"
        class="mov-total mov-total--link"
      >
        <q-icon
          name="trending_up"
          size="22px"
        />
        <span>Ver la utilidad del mes</span>
        <q-icon name="chevron_right" />
      </router-link>
    </div>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por concepto o descripción"
      :has-active-filters="hayFiltros"
      :refreshing="loading"
      @clear="limpiarFiltros"
      @refresh="refrescar"
    >
      <AppFilterPill
        v-model="tipoFilter"
        label="Tipo"
        :options="TIPO_OPCIONES"
      />
      <q-input
        v-model="mes"
        type="month"
        dense
        outlined
        aria-label="Mes"
        class="mov-mes"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="No hay movimientos en este período."
      @request="onRequest"
    >
      <template #body-cell-concepto="props">
        <q-td :props="props">
          <div class="mov-concepto">
            <q-icon
              :name="props.row.tipo === 'gasto' ? 'north_east' : 'south_west'"
              :class="`mov-icono mov-icono--${props.row.tipo}`"
            />
            <div>
              <div class="mov-concepto__nombre">
                {{ props.row.concepto?.nombre }}
              </div>
              <div
                v-if="props.row.descripcion"
                class="mov-meta"
              >
                {{ props.row.descripcion }}
              </div>
            </div>
          </div>
        </q-td>
      </template>

      <template #body-cell-metodo="props">
        <q-td :props="props">
          {{ METODO_LABEL[props.row.metodo] ?? props.row.metodo }}
          <div
            v-if="props.row.movimiento_caja_id"
            class="mov-meta"
          >
            De la caja
          </div>
        </q-td>
      </template>

      <template #body-cell-monto="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <span :class="['text-mono', 'mov-monto', `mov-monto--${props.row.tipo}`]">
            {{ props.row.tipo === 'gasto' ? '−' : '+' }}{{ formatearPrecio(props.row.monto) }}
          </span>
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <q-btn
            v-if="userStore.hasPermission('movimientos-financieros.destroy') && !props.row.movimiento_caja_id"
            flat
            dense
            round
            icon="delete_outline"
            size="sm"
            color="negative"
            :aria-label="`Eliminar ${props.row.concepto?.nombre}`"
            @click="pedirEliminar(props.row)"
          />
        </q-td>
      </template>
    </AppTable>

    <!-- ── Registrar ── -->
    <AppDialog
      v-model="formDialog"
      :title="tipoNuevo === 'gasto' ? 'Registrar gasto' : 'Registrar ingreso'"
      persistent
    >
      <MovimientoForm
        v-if="formDialog"
        ref="formRef"
        :key="`${tipoNuevo}-${aperturas}`"
        :tipo="tipoNuevo"
        @save="guardado"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="formDialog = false"
        />
        <AppButton
          variant="primary"
          label="Registrar"
          :loading="formRef?.procesando"
          @click="formRef.submit()"
        />
      </template>
    </AppDialog>

    <!-- ── Conceptos ── -->
    <AppDialog
      v-model="conceptosDialog"
      title="Conceptos de ingresos y gastos"
    >
      <ConceptosPanel v-if="conceptosDialog" />
      <template #actions>
        <AppButton
          variant="secondary"
          label="Listo"
          @click="conceptosDialog = false"
        />
      </template>
    </AppDialog>

    <!-- ── Eliminar ── -->
    <AppDialog
      v-model="eliminarDialog"
      title="Eliminar movimiento"
    >
      <p class="mov-confirmar">
        ¿Eliminar <strong>{{ aEliminar?.concepto?.nombre }}</strong> por
        <strong>{{ formatearPrecio(aEliminar?.monto) }}</strong>? Esta acción no se puede deshacer.
      </p>
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="eliminarDialog = false"
        />
        <AppButton
          variant="destructive"
          label="Eliminar"
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
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import { METODOS } from '@/modules/Caja/constantes'
import { fechaCorta, hoyLocal } from '@/modules/Cotizaciones/constantes'
import FinanzaService from '@/services/FinanzaService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import ConceptosPanel from './ConceptosPanel.vue'
import MovimientoForm from './MovimientoForm.vue'

const $q = useQuasar()
const userStore = useUserStore()

const METODO_LABEL = Object.fromEntries(METODOS.map((m) => [m.value, m.label]))
const TIPO_OPCIONES = [
  { label: 'Todos', value: null },
  { label: 'Gastos', value: 'gasto' },
  { label: 'Ingresos', value: 'ingreso' }
]

const columns = [
  { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left', sortable: true, format: (v) => fechaCorta(v), classes: 'text-mono' },
  { name: 'concepto', label: 'Concepto', field: (row) => row.concepto?.nombre, align: 'left', tarjeta: 'ancho' },
  { name: 'metodo', label: 'Método', field: 'metodo', align: 'left' },
  { name: 'usuario', label: 'Registró', field: (row) => row.usuario?.name ?? '—', align: 'left' },
  { name: 'monto', label: 'Monto', field: 'monto', align: 'right', sortable: true },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// ── Filtros: por defecto el mes en curso ──
const search = ref('')
const busqueda = ref('')
const tipoFilter = ref(null)
const mes = ref(hoyLocal().slice(0, 7))

let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const hayFiltros = computed(() => Boolean(search.value || tipoFilter.value || mes.value !== hoyLocal().slice(0, 7)))

function limpiarFiltros () {
  search.value = ''
  tipoFilter.value = null
  mes.value = hoyLocal().slice(0, 7)
}

const etiquetaPeriodo = computed(() => {
  if (!mes.value) return 'todo el período'
  const [a, m] = mes.value.split('-').map(Number)
  return new Intl.DateTimeFormat('es-PE', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(Date.UTC(a, m - 1, 1)))
})

function rangoDelMes () {
  if (!mes.value) return {}
  const [a, m] = mes.value.split('-').map(Number)
  const ultimo = new Date(Date.UTC(a, m, 0)).getUTCDate()
  return { desde: `${mes.value}-01`, hasta: `${mes.value}-${String(ultimo).padStart(2, '0')}` }
}

const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, tipo: tipoFilter.value, mes: mes.value }))

// ── Tabla ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const totales = ref({ ingresos: 0, gastos: 0 })
const pagination = ref({ sortBy: 'fecha', descending: true, page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true
  try {
    const params = { rowsPerPage, page, search: busqueda.value, order_by: descending ? `-${sortBy},-id` : `${sortBy},id`, ...rangoDelMes() }
    if (tipoFilter.value) params.tipo = tipoFilter.value
    const respuesta = await FinanzaService.movimientos({ params })
    rows.value = respuesta.data
    totales.value = respuesta.totales ?? { ingresos: 0, gastos: 0 }
    pagination.value = { ...requested, rowsNumber: respuesta.total ?? 0 }
  } finally {
    loading.value = false
  }
}

function refrescar () {
  tableRef.value.requestServerInteraction()
}

onMounted(refrescar)

// ── Registrar ──
const formDialog = ref(false)
const formRef = ref()
const tipoNuevo = ref('gasto')
const aperturas = ref(0)

function nuevo (tipo) {
  tipoNuevo.value = tipo
  aperturas.value++
  formDialog.value = true
}

function guardado (movimiento) {
  formDialog.value = false
  refrescar()
  $q.notify({
    type: 'positive',
    message: `${movimiento.tipo === 'gasto' ? 'Gasto' : 'Ingreso'} registrado: ${movimiento.concepto?.nombre} ${formatearPrecio(movimiento.monto)}.`,
    position: 'top-right',
    timeout: 2000
  })
}

const conceptosDialog = ref(false)

// ── Eliminar ──
const eliminarDialog = ref(false)
const aEliminar = ref(null)
const eliminando = ref(false)

function pedirEliminar (row) {
  aEliminar.value = row
  eliminarDialog.value = true
}

async function confirmarEliminar () {
  eliminando.value = true
  try {
    await FinanzaService.eliminar(aEliminar.value.id)
    eliminarDialog.value = false
    refrescar()
  } catch (error) {
    $q.notify({ type: 'negative', message: error.response?.data?.message ?? 'No se pudo eliminar.', position: 'top-right' })
  } finally {
    eliminando.value = false
  }
}
</script>

<style lang="scss" scoped>
.mov-totales {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}

.mov-total {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px 16px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
  background: var(--app-surface);

  strong {
    font-size: 20px;
  }

  &--gasto strong {
    color: var(--app-ink-negative);
  }

  &--ingreso strong {
    color: var(--app-ink-positive);
  }

  &--link {
    flex-direction: row;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 600;
    color: var(--app-brand-soft-ink);
    text-decoration: none;

    span {
      flex: 1;
    }

    &:hover {
      background: var(--app-brand-soft);
    }
  }
}

.mov-total__label {
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.mov-mes {
  width: 170px;

  :deep(.q-field__control) {
    height: 40px;
    border-radius: 10px;
  }
}

.mov-concepto {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.mov-concepto__nombre {
  font-weight: 600;
}

.mov-icono {
  flex-shrink: 0;
  margin-top: 2px;
  padding: 4px;
  border-radius: 8px;
  font-size: 16px;

  &--gasto {
    background: var(--app-tint-negative);
    color: var(--app-ink-negative);
  }

  &--ingreso {
    background: var(--app-tint-positive);
    color: var(--app-ink-positive);
  }
}

.mov-meta {
  font-size: 12px;
  color: var(--app-ink-2);
}

.mov-monto {
  font-weight: 700;

  &--gasto {
    color: var(--app-ink-negative);
  }

  &--ingreso {
    color: var(--app-ink-positive);
  }
}

.mov-confirmar {
  margin: 0;
  font-size: 14px;
  line-height: 1.55;
  color: var(--app-ink-2);
}
</style>
