<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Créditos"
      subtitle="Lo que te deben los clientes, en cuotas"
    />

    <div class="cred-resumen">
      <div class="cred-tile">
        <span class="cred-tile__label">Por cobrar</span>
        <strong class="text-mono">{{ formatearPrecio(resumen.por_cobrar) }}</strong>
        <span class="cred-tile__sub">{{ resumen.activos }} {{ resumen.activos === 1 ? 'crédito activo' : 'créditos activos' }}</span>
      </div>
      <div :class="['cred-tile', { 'cred-tile--alerta': resumen.vencido > 0 }]">
        <span class="cred-tile__label">Vencido</span>
        <strong class="text-mono">{{ formatearPrecio(resumen.vencido) }}</strong>
        <span class="cred-tile__sub">{{ resumen.con_vencidas }} {{ resumen.con_vencidas === 1 ? 'cliente atrasado' : 'clientes atrasados' }}</span>
      </div>
    </div>

    <div
      class="cred-tabs"
      role="tablist"
    >
      <button
        v-for="t in TABS"
        :key="t.value"
        type="button"
        role="tab"
        :aria-selected="String(estado === t.value)"
        :class="['cred-tab', { 'cred-tab--activo': estado === t.value }]"
        @click="estado = t.value"
      >
        {{ t.label }}
      </button>
    </div>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por cliente o documento"
      :refreshing="loading"
      @refresh="refrescar"
    />

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="No hay créditos en este filtro."
      @request="onRequest"
    >
      <template #body-cell-cliente="props">
        <q-td :props="props">
          <button
            type="button"
            class="cred-cliente"
            @click="ver(props.row)"
          >
            {{ props.row.cliente?.nombre }}
          </button>
          <div class="cred-meta">
            {{ props.row.pedido?.codigo }} · {{ props.row.frecuencia_label }} · {{ fechaCorta(props.row.fecha) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-cuotas="props">
        <q-td :props="props">
          <span class="text-mono">{{ props.row.cuotas_pagadas }}/{{ props.row.numero_cuotas }}</span>
          <div class="cred-progreso">
            <span :style="{ width: `${(props.row.cuotas_pagadas / props.row.numero_cuotas) * 100}%` }" />
          </div>
        </q-td>
      </template>

      <template #body-cell-estado="props">
        <q-td :props="props">
          <AppChip
            :status="estadoDe(props.row).status"
            :label="estadoDe(props.row).label"
          />
          <div
            v-if="props.row.proxima"
            class="cred-meta"
          >
            Próxima: {{ fechaCorta(props.row.proxima.vence) }} · {{ formatearPrecio(props.row.proxima.falta) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-saldo="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <strong class="text-mono">{{ formatearPrecio(props.row.saldo) }}</strong>
          <div class="cred-meta">
            de {{ formatearPrecio(props.row.monto) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <q-btn
            v-if="recordatorio(props.row)"
            flat
            dense
            round
            size="sm"
            icon="mdi-whatsapp"
            color="positive"
            :href="recordatorio(props.row)"
            target="_blank"
            rel="noopener"
            :aria-label="`Recordar el pago a ${props.row.cliente?.nombre}`"
          />
          <q-btn
            flat
            dense
            round
            size="sm"
            icon="visibility"
            color="grey-7"
            :aria-label="`Ver crédito de ${props.row.cliente?.nombre}`"
            @click="ver(props.row)"
          />
        </q-td>
      </template>
    </AppTable>

    <!-- ── Detalle ── -->
    <AppDialog
      v-model="detalleDialog"
      :title="detalle ? `Crédito de ${detalle.cliente?.nombre}` : 'Crédito'"
      size="lg"
    >
      <div
        v-if="!detalle"
        class="cred-cargando"
      >
        <q-spinner size="24px" />
      </div>
      <div
        v-else
        class="cred-detalle"
      >
        <div class="cred-detalle__cifras">
          <div><span>Financiado</span><strong class="text-mono">{{ formatearPrecio(detalle.monto) }}</strong></div>
          <div><span>Pagado</span><strong class="text-mono">{{ formatearPrecio(detalle.pagado) }}</strong></div>
          <div><span>Saldo</span><strong class="text-mono">{{ formatearPrecio(detalle.saldo) }}</strong></div>
        </div>
        <p class="cred-meta">
          {{ detalle.frecuencia_label }} · {{ detalle.numero_cuotas }} cuotas ·
          {{ detalle.orden ? `Orden ${detalle.orden.codigo}` : `Pedido ${detalle.pedido.codigo}` }}
          <template v-if="detalle.usuario">
            · otorgado por {{ detalle.usuario.name }}
          </template>
          <template v-if="detalle.observacion">
            · {{ detalle.observacion }}
          </template>
        </p>

        <AppTimeline :items="cronograma" />

        <div v-if="detalle.pagos?.length">
          <h4 class="cred-h">
            Pagos
          </h4>
          <div
            v-for="p in detalle.pagos"
            :key="p.id"
            class="cred-pago"
          >
            <span>{{ formatearFechaHora(p.fecha) }} · {{ p.metodo_label }}<template v-if="p.usuario"> · {{ p.usuario.name }}</template></span>
            <span class="text-mono">{{ formatearPrecio(p.monto) }}</span>
          </div>
        </div>
      </div>

      <template #actions>
        <AppButton
          v-if="detalle && recordatorio(detalle)"
          variant="tertiary"
          label="Recordar por WhatsApp"
          icon="mdi-whatsapp"
          :href="recordatorio(detalle)"
          target="_blank"
        />
        <AppButton
          v-if="detalle && Number(detalle.saldo) > 0 && userStore.hasPermission('pedidos.pagos')"
          variant="primary"
          label="Cobrar cuota"
          icon="payments"
          @click="abrirPago"
        />
      </template>
    </AppDialog>

    <AppDialog
      v-model="pagoDialog"
      title="Cobrar cuota"
      persistent
    >
      <PagoForm
        v-if="pagoDialog && detalle"
        ref="pagoRef"
        :key="`pago-${detalle.id}-${aperturasPago}`"
        :pedido="{ id: detalle.pedido.id, codigo: detalle.pedido.codigo, saldo: detalle.pedido_saldo, pagado: detalle.pagado }"
        modo="cobrar"
        @save="pagoGuardado"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="pagoDialog = false"
        />
        <AppButton
          variant="primary"
          label="Cobrar"
          :loading="pagoRef?.form.processing"
          @click="pagoRef.submit()"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import AppTimeline from '@/components/AppTimeline.vue'
import { TIENDA } from '@/config/tienda'
import PagoForm from '@/modules/Caja/PagoForm.vue'
import { fechaCorta } from '@/modules/Cotizaciones/constantes'
import CreditoService from '@/services/CreditoService'
import { useUserStore } from '@/stores/user-store'
import { formatearFechaHora } from '@/utils/fechas'
import { formatearPrecio } from '@/utils/moneda'

const $q = useQuasar()
const route = useRoute()
const userStore = useUserStore()

const TABS = [
  { value: 'activos', label: 'Activos' },
  { value: 'vencidos', label: 'Con cuotas vencidas' },
  { value: 'pagados', label: 'Pagados' },
  { value: 'todos', label: 'Todos' }
]

const ESTADO_CUOTA = {
  pagada: { color: 'positive', label: 'Pagada', icon: 'check' },
  parcial: { color: 'info', label: 'Pago parcial', icon: 'timelapse' },
  pendiente: { color: 'neutral', label: 'Pendiente', icon: 'schedule' },
  vencida: { color: 'negative', label: 'Vencida', icon: 'priority_high' }
}

// Cronograma: pagadas con check, vencidas en rojo y la próxima por cobrar
// resaltada; las que siguen, en gris.
const cronograma = computed(() => {
  const cuotas = detalle.value?.cuotas ?? []
  const proxima = cuotas.find((c) => c.estado !== 'pagada')
  return cuotas.map((c) => {
    const e = ESTADO_CUOTA[c.estado]
    const enCurso = c.estado === 'vencida' || c === proxima
    return {
      key: c.id,
      titulo: `Cuota ${c.numero}`,
      extra: formatearPrecio(c.monto),
      fecha: `${c.estado === 'pagada' ? 'Venció' : 'Vence'} el ${fechaCorta(c.vence)}`,
      descripcion: c.estado === 'pagada' ? e.label : `${e.label} · falta ${formatearPrecio(c.falta)}`,
      icon: e.icon,
      color: e.color,
      estado: c.estado === 'pagada' ? 'hecho' : enCurso ? 'actual' : 'pendiente'
    }
  })
})

function estadoDe (credito) {
  if (Number(credito.saldo) <= 0) return { status: 'positive', label: 'Pagado' }
  if (credito.cuotas_vencidas) return { status: 'negative', label: `${credito.cuotas_vencidas} vencida${credito.cuotas_vencidas > 1 ? 's' : ''}` }
  return { status: 'info', label: 'Al día' }
}

const columns = [
  { name: 'cliente', label: 'Cliente', field: (row) => row.cliente?.nombre, align: 'left' },
  { name: 'cuotas', label: 'Cuotas', field: 'cuotas_pagadas', align: 'left' },
  { name: 'estado', label: 'Estado', field: 'cuotas_vencidas', align: 'left' },
  { name: 'saldo', label: 'Saldo', field: 'saldo', align: 'right', tarjeta: 'monto' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// ?estado=vencidos (desde la campana) abre en esa pestaña.
const estado = ref(TABS.some((t) => t.value === route.query.estado) ? route.query.estado : 'activos')
const search = ref('')
const busqueda = ref('')
let searchTimer
watch(search, (v) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = v.trim() }, 400)
})

const filtroTabla = computed(() => JSON.stringify({ estado: estado.value, search: busqueda.value }))

const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const resumen = ref({ por_cobrar: 0, vencido: 0, activos: 0, con_vencidas: 0 })
const pagination = ref({ sortBy: null, descending: false, page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  loading.value = true
  try {
    const respuesta = await CreditoService.getData({
      params: { estado: estado.value, search: busqueda.value, page: requested.page, rowsPerPage: requested.rowsPerPage }
    })
    rows.value = respuesta.data
    resumen.value = respuesta.resumen
    pagination.value = { ...requested, rowsNumber: respuesta.total }
  } finally {
    loading.value = false
  }
}

function refrescar () {
  tableRef.value.requestServerInteraction()
}

onMounted(refrescar)

/** wa.me con el recordatorio de la próxima cuota. */
function recordatorio (credito) {
  let numero = (credito.cliente?.telefono ?? '').replace(/\D/g, '')
  if (!numero || !credito.proxima) return null
  if (numero.length === 9) numero = `51${numero}`
  const nombre = credito.cliente.nombre.split(' ')[0]
  const c = credito.proxima
  const texto = c.estado === 'vencida'
    ? `Hola ${nombre}, te recordamos que tu cuota ${c.numero} de ${formatearPrecio(c.falta)} venció el ${fechaCorta(c.vence)}. ¿Cuándo podrías pasar a pagarla?`
    : `Hola ${nombre}, te recordamos que tu cuota ${c.numero} de ${formatearPrecio(c.falta)} vence el ${fechaCorta(c.vence)}.`
  return `https://wa.me/${numero}?text=${encodeURIComponent(`${texto}\n— ${TIENDA.nombre}`)}`
}

// ── Detalle ──
const detalleDialog = ref(false)
const detalle = ref(null)

async function ver (credito) {
  detalle.value = null
  detalleDialog.value = true
  detalle.value = await CreditoService.get(credito.id)
}

const pagoDialog = ref(false)
const pagoRef = ref()
const aperturasPago = ref(0)

function abrirPago () {
  aperturasPago.value++
  pagoDialog.value = true
}

async function pagoGuardado () {
  pagoDialog.value = false
  detalle.value = await CreditoService.get(detalle.value.id)
  refrescar()
  $q.notify({ type: 'positive', message: 'Cuota cobrada.', position: 'top-right', timeout: 1500 })
}
</script>

<style lang="scss" scoped>
.cred-resumen {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}

.cred-tile {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px 16px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
  background: var(--app-surface);

  strong {
    font-size: 22px;
    color: var(--app-ink);
  }

  &--alerta {
    border-color: var(--app-negative-border);
    background: var(--app-negative-soft);

    strong {
      color: var(--app-ink-negative);
    }
  }
}

.cred-tile__label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink-2);
}

.cred-tile__sub {
  font-size: 12px;
  color: var(--app-ink-2);
}

.cred-tabs {
  display: flex;
  gap: 6px;
  overflow-x: auto;
}

.cred-tab {
  flex-shrink: 0;
  padding: 7px 14px;
  border: 1px solid var(--app-border-control);
  border-radius: 999px;
  background: var(--app-surface);
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &--activo {
    border-color: var(--q-primary);
    background: var(--app-brand-soft);
    color: var(--app-brand-soft-ink);
  }
}

.cred-cliente {
  padding: 0;
  border: 0;
  background: none;
  font-weight: 700;
  color: var(--app-ink);
  text-align: left;
  cursor: pointer;
}

.cred-meta {
  margin: 0;
  font-size: 12px;
  color: var(--app-ink-2);
}

.cred-progreso {
  width: 80px;
  height: 5px;
  margin-top: 4px;
  border-radius: 3px;
  background: var(--app-brand-soft);
  overflow: hidden;

  span {
    display: block;
    height: 100%;
    background: var(--q-primary);
  }
}

.cred-cargando {
  display: flex;
  justify-content: center;
  padding: 24px;
}

.cred-detalle {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cred-detalle__cifras {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;

  div {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 10px 12px;
    border-radius: 10px;
    background: var(--app-page);
    font-size: 12px;
    color: var(--app-ink-2);
  }

  strong {
    font-size: 16px;
    color: var(--app-ink);
  }
}

.cred-h {
  margin: 0 0 6px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--app-ink-2);
}

.cred-pago {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 4px 0;
  font-size: 12.5px;
  color: var(--app-ink-2);
}
</style>
