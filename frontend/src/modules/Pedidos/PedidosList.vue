<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Pedidos"
      :subtitle="`${pagination.rowsNumber} pedidos`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('pedidos.store')"
          variant="primary"
          label="Nuevo pedido"
          icon="add_shopping_cart"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por código, cliente o documento"
      :has-active-filters="hayFiltros"
      :refreshing="loading"
      @clear="limpiarFiltros"
      @refresh="tableRef.actualizar()"
    >
      <AppFilterPill
        v-model="estadoFilter"
        label="Estado"
        :options="estadoOptions"
      />
      <AppFilterPill
        v-model="canalFilter"
        label="Canal"
        :options="canalOptions"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="No hay pedidos que coincidan."
      @request="onRequest"
    >
      <template #body-cell-codigo="props">
        <q-td :props="props">
          <button
            type="button"
            class="pedido-codigo text-mono"
            @click="ver(props.row)"
          >
            {{ props.row.codigo }}
          </button>
          <div class="pedido-detalle">
            {{ formatearFecha(props.row.fecha) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-cliente="props">
        <q-td :props="props">
          <span v-if="props.row.cliente">{{ props.row.cliente.nombre }}</span>
          <span
            v-else
            class="pedido-detalle"
          >Cliente varios</span>
        </q-td>
      </template>

      <template #body-cell-total="props">
        <q-td
          :props="props"
          class="text-right text-mono"
        >
          {{ formatearPrecio(props.row.total) }}
        </q-td>
      </template>

      <template #body-cell-saldo="props">
        <q-td
          :props="props"
          class="text-right text-mono"
        >
          <span
            v-if="Number(props.row.saldo) > 0 && !['cancelado', 'anulado'].includes(props.row.estado)"
            class="pedido-saldo"
          >{{ formatearPrecio(props.row.saldo) }}</span>
          <span
            v-else
            class="pedido-detalle"
          >—</span>
        </q-td>
      </template>

      <template #body-cell-estado="props">
        <q-td :props="props">
          <AppChip
            :status="ESTADOS[props.row.estado].status"
            :label="ESTADOS[props.row.estado].label"
          />
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right"
        >
          <q-btn
            flat
            dense
            round
            icon="visibility"
            size="sm"
            color="grey-7"
            :aria-label="`Ver ${props.row.codigo}`"
            @click="ver(props.row)"
          />
          <q-btn
            v-if="props.row.editable && userStore.hasPermission('pedidos.update')"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar ${props.row.codigo}`"
            @click="editar(props.row)"
          />
        </q-td>
      </template>
    </AppTable>

    <!-- ── Crear / editar ── -->
    <AppDialog
      v-model="formDialog"
      :title="editId ? 'Editar pedido' : 'Nuevo pedido'"
      size="lg"
      persistent
    >
      <PedidoForm
        :id="editId"
        ref="formRef"
        :key="editId ?? `nuevo-${aperturas}`"
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
          label="Guardar"
          :loading="formRef?.form.processing"
          @click="formRef.submit()"
        />
      </template>
    </AppDialog>

    <!-- ── Detalle con acciones según estado y permiso ── -->
    <AppDialog
      v-model="detalleDialog"
      title="Pedido"
      size="lg"
    >
      <PedidoDetalle
        v-if="detalleId"
        ref="detalleRef"
        :key="detalleId"
        :id="detalleId"
      />

      <!-- A la izquierda lo que deshace o reimprime; a la derecha el paso
           siguiente del pedido, con el principal al final. -->
      <template #actions>
        <AppButton
          v-if="puede('cancelar')"
          variant="destructive"
          label="Cancelar pedido"
          icon="block"
          :disable="Number(detalle.pagado) > 0"
          @click="pedirConfirmacion('cancelar')"
        >
          <q-tooltip v-if="Number(detalle.pagado) > 0">
            Tiene pagos: devolvelos antes de cancelar
          </q-tooltip>
        </AppButton>
        <!-- Venta ya hecha: vuelven stock y plata. Con boleta o factura no
             (va con nota de crédito ante SUNAT). -->
        <AppButton
          v-if="puede('anular')"
          variant="destructive"
          label="Anular venta"
          icon="undo"
          :disable="Boolean(motivoNoAnulable)"
          @click="abrirAnular"
        >
          <q-tooltip v-if="motivoNoAnulable">
            {{ motivoNoAnulable }}
          </q-tooltip>
        </AppButton>
        <AppButton
          v-if="puede('devolver')"
          variant="tertiary"
          label="Devolver pago"
          icon="undo"
          @click="abrirPago('devolver')"
        />
        <AppButton
          v-if="detalle && ['confirmado', 'entregado'].includes(detalle.estado)"
          variant="tertiary"
          label="Ticket"
          icon="print"
          @click="impresionRef.imprimir(detalle)"
        />

        <span class="pedido-acciones__espacio" />

        <AppButton
          v-if="puede('editar')"
          variant="secondary"
          label="Editar"
          icon="edit"
          @click="editar(detalle)"
        />
        <AppButton
          v-if="puede('cobrar')"
          variant="secondary"
          label="Cobrar"
          icon="payments"
          @click="abrirPago('cobrar')"
        />
        <AppButton
          v-if="puede('confirmar')"
          variant="primary"
          label="Confirmar"
          icon="check"
          @click="pedirConfirmacion('confirmar')"
        />
        <AppButton
          v-if="puede('entregar')"
          variant="primary"
          label="Marcar entregado"
          icon="local_shipping"
          :disable="Number(detalle.saldo) > 0"
          :loading="accionando === 'entregar'"
          @click="ejecutar('entregar')"
        >
          <q-tooltip v-if="Number(detalle.saldo) > 0">
            Falta cobrar {{ formatearPrecio(detalle.saldo) }}
          </q-tooltip>
        </AppButton>
      </template>
    </AppDialog>

    <ImpresionTicket ref="impresionRef" />

    <!-- ── Anular venta: pide el motivo ── -->
    <AppDialog
      v-model="anularDialog"
      title="Anular venta"
      persistent
    >
      <p class="confirm-text">
        Vas a anular <strong>{{ detalle?.codigo }}</strong> por <strong>{{ formatearPrecio(detalle?.total) }}</strong>:
      </p>
      <ul class="anular-lista">
        <li>Los productos vuelven al stock de {{ detalle?.sede?.nombre ?? 'la sede' }} (a sus mismos lotes).</li>
        <li v-if="Number(detalle?.pagado) > 0">
          Se devuelven <strong>{{ formatearPrecio(detalle.pagado) }}</strong> desde la caja abierta de hoy, por el mismo medio con que se pagó.
        </li>
        <li>No se puede deshacer.</li>
      </ul>
      <AppTextField
        v-model="motivo"
        label="Motivo"
        placeholder="El cliente devolvió la mercadería"
        maxlength="255"
        :error="errorAnular"
        autofocus
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Volver"
          :disable="accionando === 'anular'"
          @click="anularDialog = false"
        />
        <AppButton
          variant="destructive"
          label="Anular venta"
          :disable="!motivo.trim()"
          :loading="accionando === 'anular'"
          @click="anular"
        />
      </template>
    </AppDialog>

    <!-- ── Cobrar / devolver ── -->
    <AppDialog
      v-model="pagoDialog"
      :title="modoPago === 'devolver' ? 'Devolver pago' : 'Cobrar pedido'"
      persistent
    >
      <!-- Cobrar sin stock se permite (adelanto de un encargo), pero avisado. -->
      <template v-if="modoPago === 'cobrar'">
        <div
          v-if="faltantes.length"
          class="pedido-faltantes"
          role="alert"
        >
          <q-icon
            name="warning"
            size="18px"
          />
          <div>
            <p>Falta stock en tu sede: el cobro queda como adelanto y el pedido no se va a poder confirmar hasta que entre la mercadería.</p>
            <ul>
              <li
                v-for="f in faltantes"
                :key="f.id"
              >
                <strong>{{ f.nombre }}</strong>: pide {{ f.pide }}, hay {{ f.hay }}
              </li>
            </ul>
          </div>
        </div>
      </template>
      <PagoForm
        v-if="pagoDialog && detalle"
        ref="pagoRef"
        :key="`${modoPago}-${detalle.id}-${aperturasPago}`"
        :pedido="detalle"
        :modo="modoPago"
        @save="pagoGuardado"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="pagoDialog = false"
        />
        <AppButton
          :variant="modoPago === 'devolver' ? 'destructive' : 'primary'"
          :label="modoPago === 'devolver' ? 'Devolver' : 'Cobrar'"
          :loading="pagoRef?.form.processing"
          @click="pagoRef.submit()"
        />
      </template>
    </AppDialog>

    <!-- ── Confirmar / cancelar: mueven stock, se pregunta antes ── -->
    <AppDialog
      v-model="accionDialog"
      :title="accionPendiente === 'confirmar' ? 'Confirmar pedido' : 'Cancelar pedido'"
    >
      <p class="confirm-text">
        <template v-if="accionPendiente === 'confirmar'">
          Al confirmar <strong>{{ detalle?.codigo }}</strong> se descuenta el stock de sus productos y ya no se puede editar.
        </template>
        <template v-else-if="detalle?.estado === 'confirmado'">
          ¿Cancelar <strong>{{ detalle?.codigo }}</strong>? Sus productos vuelven al stock.
        </template>
        <template v-else>
          ¿Cancelar <strong>{{ detalle?.codigo }}</strong>? Todavía no había descontado stock.
        </template>
      </p>
      <template v-if="accionPendiente === 'confirmar'">
        <div
          v-if="faltantes.length"
          class="pedido-faltantes"
          role="alert"
        >
          <q-icon
            name="warning"
            size="18px"
          />
          <div>
            <p>No se puede confirmar todavía: falta stock en tu sede. Registrá la entrada de mercadería o editá las cantidades.</p>
            <ul>
              <li
                v-for="f in faltantes"
                :key="f.id"
              >
                <strong>{{ f.nombre }}</strong>: pide {{ f.pide }}, hay {{ f.hay }}
              </li>
            </ul>
          </div>
        </div>
      </template>

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Volver"
          @click="accionDialog = false"
        />
        <AppButton
          :variant="accionPendiente === 'cancelar' ? 'destructive' : 'primary'"
          :label="accionPendiente === 'confirmar' ? 'Confirmar' : 'Cancelar pedido'"
          :loading="accionando === accionPendiente"
          :disable="accionPendiente === 'confirmar' && faltantes.length > 0"
          @click="ejecutar(accionPendiente)"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import AppTextField from '@/components/AppTextField.vue'
import PagoForm from '@/modules/Caja/PagoForm.vue'
import ImpresionTicket from '@/modules/Ventas/ImpresionTicket.vue'
import PedidoService from '@/services/PedidoService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import PedidoDetalle from './PedidoDetalle.vue'
import PedidoForm from './PedidoForm.vue'
import { CANALES, ESTADOS } from './constantes'

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const columns = [
  { name: 'codigo', label: 'Pedido', field: 'codigo', align: 'left', sortable: true },
  { name: 'cliente', label: 'Cliente', field: (row) => row.cliente?.nombre, align: 'left' },
  { name: 'sede', label: 'Sede', field: (row) => row.sede?.nombre ?? '—', align: 'left' },
  { name: 'canal', label: 'Canal', field: 'canal_label', align: 'left' },
  { name: 'items_count', label: 'Ítems', field: 'items_count', align: 'right', classes: 'text-mono' },
  { name: 'total', label: 'Total', field: 'total', align: 'right', sortable: true },
  { name: 'saldo', label: 'Saldo', field: 'saldo', align: 'right' },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })
function formatearFecha (iso) {
  return iso ? formatoFecha.format(new Date(iso)) : ''
}

// ── Filtros ──
const search = ref('')
const busqueda = ref('')
const estadoFilter = ref(null)
const canalFilter = ref(null)

let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const estadoOptions = [
  { label: 'Todos', value: null },
  ...Object.entries(ESTADOS).map(([value, { label }]) => ({ label, value }))
]
const canalOptions = [{ label: 'Todos', value: null }, ...CANALES]

const hayFiltros = computed(() => Boolean(search.value || estadoFilter.value || canalFilter.value))

function limpiarFiltros () {
  search.value = ''
  estadoFilter.value = null
  canalFilter.value = null
}

// AppTable vuelve a la página 1 y pide datos cuando cambia `filter`.
const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, estado: estadoFilter.value, canal: canalFilter.value }))

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
// El más nuevo primero: "codigo" ordena por id (mismo orden, sin empates).
const pagination = ref({ sortBy: 'codigo', descending: true, page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true

  try {
    const columna = sortBy === 'codigo' ? 'id' : sortBy
    const params = { rowsPerPage, page, search: busqueda.value, order_by: descending ? `-${columna}` : columna }
    if (estadoFilter.value) params.estado = estadoFilter.value
    if (canalFilter.value) params.canal = canalFilter.value

    const { data, total = 0 } = await PedidoService.getData({ params })

    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
})

function refrescar () {
  tableRef.value.requestServerInteraction()
}

// ── Crear / editar ──
const formDialog = ref(false)
const formRef = ref()
const editId = ref(null)
const aperturas = ref(0)

function crear () {
  editId.value = null
  aperturas.value++
  formDialog.value = true
}

function editar (pedido) {
  detalleDialog.value = false
  editId.value = pedido.id
  formDialog.value = true
}

// Recién guardado: se abre su detalle, que es donde se confirma.
function guardado (pedido) {
  formDialog.value = false
  refrescar()
  $q.notify({ type: 'positive', message: `Pedido ${pedido?.codigo ?? ''} guardado.`, position: 'top-right', timeout: 1500 })
  if (pedido) ver(pedido)
}

const impresionRef = ref()

// ── Detalle y acciones ──
const detalleDialog = ref(false)
const detalleRef = ref()
const detalleId = ref(null)
const detalle = computed(() => detalleRef.value?.pedido ?? null)

function ver (pedido) {
  detalleId.value = pedido.id
  detalleDialog.value = true
}

// ?ver=ID (desde la búsqueda global o la campana) abre ese detalle, también
// si ya estábamos en esta pantalla.
watch(() => route.query.ver, (id) => {
  if (!id) return
  ver({ id: Number(id) })
  router.replace({ query: { ...route.query, ver: undefined } })
}, { immediate: true })

const REGLAS = {
  editar: { estados: ['pendiente'], permiso: 'pedidos.update' },
  // Un pendiente puede recibir un adelanto.
  cobrar: { estados: ['pendiente', 'confirmado'], permiso: 'pedidos.pagos', saldo: true },
  devolver: { estados: ['pendiente', 'confirmado'], permiso: 'pedidos.devoluciones', pagado: true },
  confirmar: { estados: ['pendiente'], permiso: 'pedidos.confirmar' },
  entregar: { estados: ['confirmado'], permiso: 'pedidos.entregar' },
  cancelar: { estados: ['pendiente', 'confirmado'], permiso: 'pedidos.cancelar' },
  // Venta ya hecha (también las del punto de venta).
  anular: { estados: ['confirmado', 'entregado'], permiso: 'pedidos.anular' }
}

function puede (accion) {
  const regla = REGLAS[accion]
  const p = detalle.value
  return Boolean(p) &&
    regla.estados.includes(p.estado) &&
    userStore.hasPermission(regla.permiso) &&
    (!regla.saldo || Number(p.saldo) > 0) &&
    (!regla.pagado || Number(p.pagado) > 0)
}

// ── Cobrar / devolver ──
const pagoDialog = ref(false)
const pagoRef = ref()
const modoPago = ref('cobrar')
const aperturasPago = ref(0)

function abrirPago (modo) {
  modoPago.value = modo
  aperturasPago.value++
  pagoDialog.value = true
}

async function pagoGuardado () {
  pagoDialog.value = false
  detalleRef.value.actualizar(await PedidoService.get(detalle.value.id))
  refrescar()
  $q.notify({
    type: 'positive',
    message: modoPago.value === 'devolver' ? 'Devolución registrada.' : 'Pago registrado.',
    position: 'top-right',
    timeout: 1800
  })
}

// Ítems de un pendiente que hoy no alcanzan con el stock de la sede del pedido
// (el detalle trae ese stock). Confirmado ya descontó: no aplica.
const faltantes = computed(() => {
  if (detalle.value?.estado !== 'pendiente') return []
  return (detalle.value.items ?? [])
    .filter((item) => Number(item.cantidad) > Number(item.variante?.stock ?? 0))
    .map((item) => ({
      id: item.id,
      nombre: [item.variante?.producto?.nombre, item.variante?.presentacion].filter(Boolean).join(' '),
      pide: Number(item.cantidad),
      hay: Number(item.variante?.stock ?? 0)
    }))
})

const accionDialog = ref(false)
const accionPendiente = ref(null)
const accionando = ref(null)

// ── Anular venta ──
// Con boleta o factura vigente no se anula acá: va con nota de crédito.
const motivoNoAnulable = computed(() => {
  const c = detalle.value?.comprobante
  if (!c || c.estado === 'rechazado') return null
  return `Tiene la ${c.numero}: una venta con boleta o factura se anula con nota de crédito ante SUNAT (próximamente).`
})

const anularDialog = ref(false)
const motivo = ref('')
const errorAnular = ref('')

function abrirAnular () {
  motivo.value = ''
  errorAnular.value = ''
  anularDialog.value = true
}

async function anular () {
  accionando.value = 'anular'
  errorAnular.value = ''
  try {
    const actualizado = await PedidoService.anular(detalle.value.id, motivo.value.trim())
    detalleRef.value.actualizar(actualizado)
    anularDialog.value = false
    refrescar()
    $q.notify({ type: 'positive', message: `Venta ${actualizado.codigo} anulada: volvieron el stock y la plata.`, position: 'top-right', timeout: 3500 })
  } catch (error) {
    const { status, data } = error.response ?? {}
    const primero = Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message
    if (status === 422 && data?.errors?.motivo) {
      errorAnular.value = data.errors.motivo[0]
    } else if (status === 422 || status === 409) {
      anularDialog.value = false
      $q.notify({ type: 'negative', message: primero, position: 'top-right', timeout: 6000, multiLine: true })
    }
  } finally {
    accionando.value = null
  }
}

function pedirConfirmacion (accion) {
  accionPendiente.value = accion
  accionDialog.value = true
}

const MENSAJES = {
  confirmar: 'Pedido confirmado: se descontó el stock.',
  entregar: 'Pedido entregado.',
  cancelar: 'Pedido cancelado.'
}

async function ejecutar (accion) {
  accionando.value = accion
  try {
    const actualizado = await PedidoService[accion](detalle.value.id)
    detalleRef.value.actualizar(actualizado)
    accionDialog.value = false
    refrescar()
    $q.notify({ type: 'positive', message: MENSAJES[accion], position: 'top-right', timeout: 2000 })
  } catch (error) {
    accionDialog.value = false
    // 422: falta stock en algún ítem; 409: el estado cambió mientras tanto.
    const { status, data } = error.response ?? {}
    if (status === 422 || status === 409) {
      // Todos los errores, no sólo el primero: con varios ítems sin stock hay
      // que saber cuáles son.
      const mensajes = Object.values(data?.errors ?? {}).map((m) => m[0])
      $q.notify({
        type: 'negative',
        message: mensajes.length ? mensajes.join(' · ') : data?.message,
        multiLine: true,
        position: 'top-right',
        timeout: 6000
      })
      if (status === 409) detalleRef.value.actualizar(await PedidoService.get(detalle.value.id))
    }
  } finally {
    accionando.value = null
  }
}
</script>

<style lang="scss" scoped>
.anular-lista {
  margin: 0 0 12px;
  padding-left: 18px;
  font-size: 13px;
  line-height: 1.6;
  color: var(--app-ink-2);
}

// Separa los dos grupos de botones del detalle (ver template).
.pedido-acciones__espacio {
  flex: 1 1 auto;
}

.pedido-faltantes {
  display: flex;
  gap: 10px;
  margin-bottom: 14px;
  padding: 12px 14px;
  border: 1px solid rgba($warning, 0.45);
  border-radius: 10px;
  background: rgba($warning, 0.12);
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink);

  p {
    margin: 0 0 4px;
  }

  ul {
    margin: 0;
    padding-left: 18px;
  }
}

.pedido-codigo {
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

.pedido-detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.pedido-saldo {
  font-weight: 600;
  color: var(--q-warning);
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
