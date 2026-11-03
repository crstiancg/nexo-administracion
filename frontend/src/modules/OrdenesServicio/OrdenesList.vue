<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Órdenes de servicio"
      :subtitle="`${pagination.rowsNumber} órdenes`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('ordenes-servicio.store')"
          variant="primary"
          label="Recibir equipo"
          icon="add"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <!-- Contadores por estado: también son el filtro principal. -->
    <div
      class="os-estados"
      role="tablist"
      aria-label="Filtrar por estado"
    >
      <button
        v-for="tab in tabs"
        :key="tab.value ?? 'todas'"
        type="button"
        role="tab"
        :aria-selected="String(estadoFilter === tab.value)"
        :class="['os-estados__tab', { 'os-estados__tab--activo': estadoFilter === tab.value }]"
        @click="estadoFilter = tab.value"
      >
        <q-icon
          v-if="tab.icon"
          :name="tab.icon"
          size="16px"
        />
        <span>{{ tab.label }}</span>
        <span class="os-estados__total text-mono">{{ tab.total }}</span>
      </button>
    </div>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por código, cliente, teléfono, serie o modelo"
      :has-active-filters="hayFiltros"
      :refreshing="loading"
      @clear="limpiarFiltros"
      @refresh="refrescar"
    >
      <AppFilterPill
        v-model="tecnicoFilter"
        label="Técnico"
        :options="tecnicoOptions"
      />
    </AppFilterBar>

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filtroTabla"
      no-data-label="No hay órdenes que coincidan."
      @request="onRequest"
    >
      <template #body-cell-codigo="props">
        <q-td :props="props">
          <button
            type="button"
            class="os-codigo text-mono"
            @click="ver(props.row)"
          >
            {{ props.row.codigo }}
          </button>
          <div class="os-meta">
            {{ fechaCorta(props.row.recibido_at) }} · {{ diasEnTaller(props.row) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-cliente="props">
        <q-td :props="props">
          <div>{{ props.row.cliente?.nombre }}</div>
          <div
            v-if="props.row.cliente?.telefono"
            class="os-meta"
          >
            {{ props.row.cliente.telefono }}
          </div>
        </q-td>
      </template>

      <template #body-cell-equipo="props">
        <q-td :props="props">
          <div class="os-equipo">
            {{ props.row.equipo }}
          </div>
          <div class="os-meta os-falla">
            {{ props.row.falla }}
          </div>
        </q-td>
      </template>

      <template #body-cell-estado="props">
        <q-td :props="props">
          <AppChip
            :status="ESTADOS[props.row.estado].status"
            :label="ESTADOS[props.row.estado].label"
          />
          <div
            v-if="props.row.es_garantia"
            class="os-meta"
          >
            Por garantía
          </div>
        </q-td>
      </template>

      <template #body-cell-saldo="props">
        <q-td
          :props="props"
          class="text-right text-mono"
        >
          <span :class="{ 'os-saldo--debe': Number(props.row.saldo) > 0 }">{{ formatearPrecio(props.row.saldo) }}</span>
          <div class="os-meta">
            de {{ formatearPrecio(props.row.total) }}
          </div>
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right os-acciones"
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
            v-if="enlaceWhatsapp(props.row)"
            flat
            dense
            round
            icon="mdi-whatsapp"
            size="sm"
            color="positive"
            :href="enlaceWhatsapp(props.row)"
            target="_blank"
            rel="noopener"
            :aria-label="`Avisar por WhatsApp a ${props.row.cliente?.nombre}`"
          />
          <q-btn
            v-if="props.row.editable && userStore.hasPermission('ordenes-servicio.update')"
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

    <!-- ── Recibir / editar ── -->
    <AppDialog
      v-model="formDialog"
      :title="editId ? 'Editar orden' : 'Recibir equipo'"
      size="lg"
      persistent
    >
      <OrdenForm
        :id="editId"
        ref="formRef"
        :key="editId ?? `nueva-${aperturas}`"
        @save="guardada"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="formDialog = false"
        />
        <AppButton
          variant="primary"
          :label="editId ? 'Guardar cambios' : 'Registrar orden'"
          :loading="formRef?.form.processing"
          @click="formRef.submit()"
        />
      </template>
    </AppDialog>

    <!-- ── Detalle con acciones ── -->
    <AppDialog
      v-model="detalleDialog"
      title="Orden de servicio"
      size="lg"
    >
      <OrdenDetalle
        v-if="detalleId"
        :id="detalleId"
        ref="detalleRef"
        :key="detalleId"
      />

      <template #actions>
        <AppButton
          v-if="detalle"
          variant="tertiary"
          label="Imprimir"
          icon="print"
        >
          <q-menu
            anchor="top left"
            self="bottom left"
          >
            <q-list
              dense
              style="min-width: 200px"
            >
              <q-item
                v-close-popup
                clickable
                @click="imprimir(detalle, 'orden')"
              >
                <q-item-section avatar>
                  <q-icon name="description" />
                </q-item-section>
                <q-item-section>Orden de servicio (A4)</q-item-section>
              </q-item>
              <q-item
                v-close-popup
                clickable
                @click="imprimir(detalle, 'etiqueta')"
              >
                <q-item-section avatar>
                  <q-icon name="qr_code_2" />
                </q-item-section>
                <q-item-section>Etiqueta para el equipo</q-item-section>
              </q-item>
              <q-separator />
              <!-- La misma página que abre el QR: para verla o mandarla. -->
              <q-item
                v-close-popup
                clickable
                tag="a"
                :href="enlaceSeguimiento(detalle.token)"
                target="_blank"
                rel="noopener"
              >
                <q-item-section avatar>
                  <q-icon name="open_in_new" />
                </q-item-section>
                <q-item-section>Ver página de seguimiento</q-item-section>
              </q-item>
              <q-item
                v-close-popup
                clickable
                @click="copiarSeguimiento(detalle)"
              >
                <q-item-section avatar>
                  <q-icon name="link" />
                </q-item-section>
                <q-item-section>Copiar enlace de seguimiento</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </AppButton>
        <AppButton
          v-if="detalle && enlaceWhatsapp(detalle)"
          variant="tertiary"
          label="WhatsApp"
          icon="mdi-whatsapp"
          :href="enlaceWhatsapp(detalle)"
          target="_blank"
        />
        <AppButton
          v-if="detalle && Number(detalle.saldo) > 0 && userStore.hasPermission('pedidos.pagos')"
          variant="secondary"
          label="Registrar abono"
          icon="payments"
          @click="abrirPago"
        />
        <AppButton
          v-if="detalle?.editable && userStore.hasPermission('ordenes-servicio.update')"
          variant="secondary"
          label="Editar"
          icon="edit"
          @click="editar(detalle)"
        />
        <AppButton
          v-if="detalle?.editable && userStore.hasPermission('ordenes-servicio.estado')"
          variant="primary"
          label="Cambiar estado"
          icon="sync_alt"
          @click="abrirEstado"
        />
      </template>
    </AppDialog>

    <!-- ── Cambiar estado ── -->
    <AppDialog
      v-model="estadoDialog"
      :title="`Cambiar estado de ${detalle?.codigo ?? ''}`"
      persistent
    >
      <div class="os-cambio">
        <div
          class="os-cambio__opciones"
          role="radiogroup"
          aria-label="Nuevo estado"
        >
          <button
            v-for="opcion in opcionesEstado"
            :key="opcion.value"
            type="button"
            role="radio"
            :aria-checked="String(nuevoEstado === opcion.value)"
            :disabled="opcion.disable"
            :class="['os-cambio__opcion', { 'os-cambio__opcion--activa': nuevoEstado === opcion.value }]"
            @click="nuevoEstado = opcion.value"
          >
            <q-icon
              :name="opcion.icon"
              size="18px"
            />
            {{ opcion.label }}
          </button>
        </div>
        <p
          v-if="nuevoEstado === 'entregado'"
          class="os-cambio__ayuda"
        >
          Al entregar se descuentan los repuestos del inventario y el saldo tiene que estar en cero
          <template v-if="Number(detalle?.saldo) > 0">
            (falta cobrar <strong>{{ formatearPrecio(detalle.saldo) }}</strong>)
          </template>.
        </p>
        <AppTextField
          v-model="notaEstado"
          label="Nota (opcional, queda en el historial)"
          type="textarea"
          autogrow
          maxlength="500"
        />
        <p
          v-if="errorEstado"
          class="os-cambio__error"
          role="alert"
        >
          {{ errorEstado }}
        </p>
      </div>

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="estadoDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar estado"
          :disable="!nuevoEstado"
          :loading="cambiandoEstado"
          @click="guardarEstado"
        />
      </template>
    </AppDialog>

    <!-- ── Abono: va al pedido de la orden, contra la caja abierta ── -->
    <AppDialog
      v-model="pagoDialog"
      title="Registrar abono"
      persistent
    >
      <PagoForm
        v-if="pagoDialog && detalle"
        ref="pagoRef"
        :key="`pago-${detalle.id}-${aperturasPago}`"
        :pedido="{ id: detalle.pedido_id, codigo: detalle.codigo, saldo: detalle.saldo, pagado: detalle.pagado }"
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
import { copyToClipboard, useQuasar } from 'quasar'
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
import OrdenServicioService from '@/services/OrdenServicioService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import OrdenDetalle from './OrdenDetalle.vue'
import OrdenForm from './OrdenForm.vue'
import { enlaceSeguimiento, enlaceWhatsapp, ESTADOS, fechaCorta, LISTOS } from './constantes'

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const columns = [
  { name: 'codigo', label: 'Orden', field: 'codigo', align: 'left', sortable: true },
  { name: 'cliente', label: 'Cliente', field: (row) => row.cliente?.nombre, align: 'left' },
  { name: 'equipo', label: 'Equipo / falla', field: 'equipo', align: 'left', tarjeta: 'ancho' },
  { name: 'tecnico', label: 'Técnico', field: (row) => row.tecnico?.name ?? '—', align: 'left' },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left', sortable: true },
  { name: 'saldo', label: 'Saldo', field: 'saldo', align: 'right' },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

// ── Contadores y filtros ──
const resumen = ref([])
async function cargarResumen () {
  try {
    resumen.value = await OrdenServicioService.resumen()
  } catch {
    // Los contadores no valen un error en pantalla.
  }
}

const tabs = computed(() => {
  const total = (estados) => resumen.value.filter((r) => estados.includes(r.estado)).reduce((s, r) => s + r.total, 0)
  const activos = Object.keys(ESTADOS).filter((e) => !['entregado', 'abandonado'].includes(e))
  return [
    { value: 'activas', label: 'En el taller', icon: 'build_circle', total: total(activos) },
    ...Object.entries(ESTADOS).map(([value, { label, icon }]) => ({ value, label, icon, total: total([value]) })),
    { value: null, label: 'Todas', total: total(Object.keys(ESTADOS)) }
  ]
})

const search = ref('')
const busqueda = ref('')
// ?estado=reparado (desde la campana o un enlace): abre en ese estado.
const estadoFilter = ref(['activas', ...Object.keys(ESTADOS)].includes(route.query.estado) ? route.query.estado : 'activas')
const tecnicoFilter = ref(null)
const tecnicoOptions = ref([{ label: 'Todos', value: null }])

let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { busqueda.value = value.trim() }, 400)
})

const hayFiltros = computed(() => Boolean(search.value || tecnicoFilter.value || estadoFilter.value !== 'activas'))

function limpiarFiltros () {
  search.value = ''
  tecnicoFilter.value = null
  estadoFilter.value = 'activas'
}

const filtroTabla = computed(() => JSON.stringify({ search: busqueda.value, estado: estadoFilter.value, tecnico: tecnicoFilter.value }))

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: 'codigo', descending: true, page: 1, rowsPerPage: 20, rowsNumber: 0 })

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true

  try {
    const columna = sortBy === 'codigo' ? 'id' : sortBy
    const params = { rowsPerPage, page, search: busqueda.value, order_by: descending ? `-${columna}` : columna }
    if (estadoFilter.value) params.estado = estadoFilter.value
    if (tecnicoFilter.value) params.tecnico_id = tecnicoFilter.value

    const { data, total = 0 } = await OrdenServicioService.getData({ params })
    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

function refrescar () {
  tableRef.value.requestServerInteraction()
  cargarResumen()
}

onMounted(async () => {
  tableRef.value.requestServerInteraction()
  cargarResumen()
  try {
    const tecnicos = await OrdenServicioService.tecnicos()
    tecnicoOptions.value = [{ label: 'Todos', value: null }, ...tecnicos.map((t) => ({ label: t.name, value: t.id }))]
  } catch {
    // Sin la lista, el filtro queda en "Todos".
  }
})

/** "hoy", "3 días": cuánto lleva en el taller (o cuánto estuvo). */
function diasEnTaller (orden) {
  const hasta = orden.entregado_at ? new Date(orden.entregado_at) : new Date()
  const dias = Math.floor((hasta - new Date(orden.recibido_at)) / 86400000)
  if (dias <= 0) return 'hoy'
  return dias === 1 ? '1 día' : `${dias} días`
}

// ── Recibir / editar ──
const formDialog = ref(false)
const formRef = ref()
const editId = ref(null)
const aperturas = ref(0)

function crear () {
  editId.value = null
  aperturas.value++
  formDialog.value = true
}

function editar (orden) {
  detalleDialog.value = false
  editId.value = orden.id
  formDialog.value = true
}

function guardada (orden) {
  const nueva = !editId.value
  formDialog.value = false
  refrescar()
  $q.notify({ type: 'positive', message: `Orden ${orden?.codigo ?? ''} guardada.`, position: 'top-right', timeout: 1500 })
  if (!orden) return
  ver(orden)
  // Al recibir: la orden para el cliente y la etiqueta para pegar al equipo.
  if (nueva) {
    $q.notify({
      message: '¿Imprimir la orden y la etiqueta del equipo?',
      icon: 'print',
      position: 'bottom',
      timeout: 10000,
      actions: [
        { label: 'Orden', color: 'white', handler: () => imprimir(orden, 'orden') },
        { label: 'Etiqueta', color: 'white', handler: () => imprimir(orden, 'etiqueta') }
      ]
    })
  }
}

/**
 * Abre el documento (HTML del backend, con el QR) en una pestaña nueva por un
 * enlace firmado. La pestaña se abre ANTES del await: abierta después de una
 * respuesta, el navegador la toma por un pop-up y la bloquea.
 */
async function copiarSeguimiento (orden) {
  try {
    await copyToClipboard(enlaceSeguimiento(orden.token))
    $q.notify({ type: 'positive', message: 'Enlace de seguimiento copiado.', position: 'top-right', timeout: 1500 })
  } catch {
    $q.notify({ type: 'negative', message: 'No se pudo copiar el enlace.', position: 'top-right' })
  }
}

async function imprimir (orden, formato) {
  const ventana = window.open('', '_blank')
  try {
    const enlaces = await OrdenServicioService.impresion(orden.id)
    if (ventana) ventana.location.href = enlaces[formato]
    else window.location.href = enlaces[formato]
  } catch {
    ventana?.close()
    $q.notify({ type: 'negative', message: 'No se pudo abrir el documento.', position: 'top-right' })
  }
}

// ── Detalle ──
const detalleDialog = ref(false)
const detalleRef = ref()
const detalleId = ref(null)
const detalle = computed(() => detalleRef.value?.orden ?? null)

function ver (orden) {
  detalleId.value = orden.id
  detalleDialog.value = true
}

// ?ver=ID (desde la búsqueda global o un enlace) abre ese detalle.
watch(() => route.query.ver, (id) => {
  if (!id) return
  ver({ id: Number(id) })
  router.replace({ query: { ...route.query, ver: undefined } })
}, { immediate: true })

// ── Cambiar estado ──
const estadoDialog = ref(false)
const nuevoEstado = ref(null)
const notaEstado = ref('')
const errorEstado = ref('')
const cambiandoEstado = ref(false)

const opcionesEstado = computed(() => Object.entries(ESTADOS)
  .filter(([value]) => value !== detalle.value?.estado)
  .map(([value, { label, icon }]) => ({
    value,
    label,
    icon,
    disable: value === 'entregado' && !LISTOS.includes(detalle.value?.estado)
  })))

function abrirEstado () {
  nuevoEstado.value = null
  notaEstado.value = ''
  errorEstado.value = ''
  estadoDialog.value = true
}

async function guardarEstado () {
  cambiandoEstado.value = true
  errorEstado.value = ''
  try {
    const orden = await OrdenServicioService.cambiarEstado(detalle.value.id, nuevoEstado.value, notaEstado.value)
    detalleRef.value.actualizar(orden)
    estadoDialog.value = false
    refrescar()
    const wa = enlaceWhatsapp(orden)
    $q.notify({
      type: 'positive',
      message: `${orden.codigo}: ${ESTADOS[orden.estado].label}.`,
      position: 'top-right',
      timeout: wa ? 8000 : 2000,
      actions: wa ? [{ label: 'Avisar por WhatsApp', color: 'white', handler: () => window.open(wa, '_blank', 'noopener') }] : []
    })
  } catch (error) {
    const errores = error.response?.data?.errors ?? {}
    errorEstado.value = Object.values(errores).flat()[0] ?? error.response?.data?.message ?? 'No se pudo cambiar el estado.'
  } finally {
    cambiandoEstado.value = false
  }
}

// ── Abonos ──
const pagoDialog = ref(false)
const pagoRef = ref()
const aperturasPago = ref(0)

function abrirPago () {
  aperturasPago.value++
  pagoDialog.value = true
}

async function pagoGuardado () {
  pagoDialog.value = false
  await detalleRef.value.recargar()
  refrescar()
  $q.notify({ type: 'positive', message: 'Abono registrado.', position: 'top-right', timeout: 1500 })
}
</script>

<style lang="scss" scoped>
.os-estados {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
  overflow-x: auto;
  padding-bottom: 2px;
}

.os-estados__tab {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 6px;
  height: 34px;
  padding: 0 12px;
  border: 1px solid var(--app-border-control);
  border-radius: 6px;
  background: var(--app-surface);
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    border-color: var(--app-border-control-hover);
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
}

.os-estados__tab--activo {
  border-color: $primary-hover;
  background: var(--app-brand-soft);
  color: var(--app-brand-soft-ink);
}

.os-estados__total {
  min-width: 22px;
  padding: 0 6px;
  border-radius: 4px;
  background: var(--app-border-subtle);
  font-size: 11.5px;
  text-align: center;
}

.os-codigo {
  padding: 0;
  border: 0;
  background: none;
  font-weight: 700;
  color: $primary-hover;
  cursor: pointer;
}

.os-meta {
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.os-equipo {
  font-weight: 600;
}

.os-falla {
  max-width: 280px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.os-saldo--debe {
  font-weight: 700;
  color: var(--app-ink-warning);
}

.os-acciones {
  white-space: nowrap;
}

.os-cambio {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.os-cambio__opciones {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.os-cambio__opcion {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  background: var(--app-surface);
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
  text-align: left;
  cursor: pointer;

  &:disabled {
    opacity: 0.45;
    cursor: not-allowed;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
}

.os-cambio__opcion--activa {
  border-color: $primary-hover;
  background: var(--app-brand-soft);
  color: var(--app-brand-soft-ink);
}

.os-cambio__ayuda {
  margin: 0;
  font-size: 12.5px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.os-cambio__error {
  margin: 0;
  font-size: 12.5px;
  color: var(--q-negative);
}
</style>
