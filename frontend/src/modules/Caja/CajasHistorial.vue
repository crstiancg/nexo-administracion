<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Historial de caja"
      :subtitle="`${pagination.rowsNumber} cajas`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('cajas.actual')"
          variant="tertiary"
          label="Caja actual"
          icon="point_of_sale"
          to="/caja"
        />
      </template>
    </AppPageHeader>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      no-data-label="Todavía no se abrió ninguna caja."
      @request="onRequest"
    >
      <template #body-cell-apertura="props">
        <q-td :props="props">
          <button
            type="button"
            class="caja-link"
            @click="ver(props.row)"
          >
            {{ formatearFecha(props.row.abierta_at) }}
          </button>
          <div class="caja-detalle">
            {{ props.row.abierta_por?.name ?? '—' }}
          </div>
        </q-td>
      </template>

      <template #body-cell-cierre="props">
        <q-td :props="props">
          <template v-if="props.row.cerrada_at">
            {{ formatearFecha(props.row.cerrada_at) }}
            <div class="caja-detalle">
              {{ props.row.cerrada_por?.name ?? '—' }}
            </div>
          </template>
          <AppChip
            v-else
            status="info"
            label="Abierta"
          />
        </q-td>
      </template>

      <template #body-cell-diferencia="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <span
            v-if="props.row.diferencia !== null"
            :class="['text-mono', claseDiferencia(props.row.diferencia)]"
          >
            {{ Number(props.row.diferencia) > 0 ? '+' : '' }}{{ formatearPrecio(props.row.diferencia) }}
          </span>
          <!-- Cerrada sin contar el efectivo (cierre automático a medianoche). -->
          <span v-else>{{ props.row.estado === 'cerrada' ? 'Sin arqueo' : '—' }}</span>
        </q-td>
      </template>
    </AppTable>

    <AppDialog
      v-model="detalleDialog"
      title="Detalle de caja"
      size="lg"
    >
      <CajaResumen
        v-if="detalle"
        :caja="detalle"
      />
      <div
        v-else
        class="caja-cargando"
      >
        <q-spinner size="24px" />
      </div>
    </AppDialog>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import CajaService from '@/services/CajaService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import CajaResumen from './CajaResumen.vue'

const userStore = useUserStore()

const columns = [
  { name: 'sede', label: 'Sede', field: (row) => row.sede?.nombre ?? '—', align: 'left' },
  { name: 'apertura', label: 'Apertura', field: 'abierta_at', align: 'left' },
  { name: 'cierre', label: 'Cierre', field: 'cerrada_at', align: 'left' },
  { name: 'monto_apertura', label: 'Inicial', field: (row) => formatearPrecio(row.monto_apertura), align: 'right', classes: 'text-mono' },
  { name: 'monto_esperado', label: 'Esperado', field: (row) => row.monto_esperado === null ? '—' : formatearPrecio(row.monto_esperado), align: 'right', classes: 'text-mono' },
  { name: 'monto_contado', label: 'Contado', field: (row) => row.monto_contado === null ? '—' : formatearPrecio(row.monto_contado), align: 'right', classes: 'text-mono' },
  { name: 'diferencia', label: 'Diferencia', field: 'diferencia', align: 'right' }
]

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })
function formatearFecha (iso) {
  return iso ? formatoFecha.format(new Date(iso)) : ''
}

function claseDiferencia (valor) {
  const n = Number(valor)
  if (n < 0) return 'caja-faltante'
  return n > 0 ? 'caja-sobrante' : 'caja-cuadra'
}

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage } = requested
  loading.value = true
  try {
    const { data, total = 0 } = await CajaService.getData({ params: { rowsPerPage, page, order_by: '-id' } })
    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
})

// ── Detalle ──
const detalleDialog = ref(false)
const detalle = ref(null)

async function ver (fila) {
  detalle.value = null
  detalleDialog.value = true
  detalle.value = await CajaService.get(fila.id)
}
</script>

<style lang="scss" scoped>
.caja-link {
  padding: 0;
  border: 0;
  background: none;
  font-weight: 600;
  color: $primary;
  cursor: pointer;

  &:hover {
    text-decoration: underline;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
}

.caja-detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.caja-faltante {
  font-weight: 600;
  color: var(--q-negative);
}

.caja-sobrante {
  font-weight: 600;
  color: var(--q-warning);
}

.caja-cuadra {
  color: var(--q-positive);
}

.caja-cargando {
  display: flex;
  justify-content: center;
  padding: 32px;
}
</style>
