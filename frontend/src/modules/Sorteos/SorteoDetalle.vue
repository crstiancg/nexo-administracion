<template>
  <div
    v-if="sorteo"
    class="app-list-page sorteo-detalle"
  >
    <AppPageHeader
      :title="sorteo.nombre"
      :subtitle="`Del ${fechaCorta(sorteo.inicia_el)} al ${fechaCorta(sorteo.termina_el)} · ${reglaDe(sorteo)}`"
    >
      <template #actions>
        <AppChip
          :status="ESTADOS[sorteo.estado].status"
          :label="ESTADOS[sorteo.estado].label"
        />
        <AppButton
          variant="tertiary"
          icon="arrow_back"
          label="Sorteos"
          to="/sorteos"
        />
        <AppButton
          variant="secondary"
          icon="mdi-microsoft-excel"
          label="Exportar Excel"
          :loading="exportando"
          @click="exportar"
        />
        <AppButton
          v-if="sorteo.editable && userStore.hasPermission('sorteos.update')"
          variant="secondary"
          icon="edit"
          label="Editar"
          @click="formDialog = true"
        />
        <AppButton
          v-if="sorteo.estado === 'abierto' && userStore.hasPermission('sorteos.vender')"
          variant="secondary"
          icon="confirmation_number"
          label="Vender tickets"
          @click="ventaDialog = true"
        />
        <AppButton
          v-if="userStore.hasPermission('sorteos.sortear') && ['abierto', 'por_sortear', 'sorteando', 'sorteado'].includes(sorteo.estado)"
          variant="primary"
          icon="celebration"
          :label="sorteo.estado === 'sorteado' ? 'Ver resultados en vivo' : 'Sorteo en vivo'"
          @click="abrirEnVivo"
        />
        <q-btn
          v-if="puedeCancelar || puedeEliminar"
          flat
          round
          dense
          icon="more_vert"
          aria-label="Más acciones"
        >
          <q-menu>
            <q-list dense>
              <q-item
                v-if="puedeCancelar"
                v-close-popup
                clickable
                @click="cancelar"
              >
                <q-item-section avatar>
                  <q-icon name="block" />
                </q-item-section>
                <q-item-section>Cancelar sorteo</q-item-section>
              </q-item>
              <q-item
                v-if="puedeEliminar"
                v-close-popup
                clickable
                @click="eliminar"
              >
                <q-item-section avatar>
                  <q-icon
                    name="delete_outline"
                    color="negative"
                  />
                </q-item-section>
                <q-item-section>Eliminar</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </template>
    </AppPageHeader>

    <p
      v-if="aviso"
      class="sorteo-detalle__aviso"
    >
      <q-icon :name="aviso.icono" /> {{ aviso.texto }}
    </p>

    <div class="sorteo-detalle__stats">
      <AppStatTile
        label="Tickets en juego"
        :value="String(sorteo.tickets_count)"
      />
      <AppStatTile
        label="Clientes participando"
        :value="String(sorteo.participantes_count)"
      />
      <AppStatTile
        label="Premios"
        :value="`${sorteo.premios.filter((p) => p.ganador).length} / ${sorteo.premios.length}`"
      />
    </div>

    <q-tabs
      v-model="tab"
      align="left"
      no-caps
      dense
      active-color="primary"
      indicator-color="primary"
      class="sorteo-detalle__tabs"
    >
      <q-tab
        name="premios"
        label="Premios y ganadores"
      />
      <q-tab
        name="participantes"
        :label="`Participantes (${sorteo.participantes_count})`"
      />
      <q-tab
        name="tickets"
        :label="`Tickets (${sorteo.tickets_count})`"
      />
    </q-tabs>

    <!-- ── Premios ── -->
    <section
      v-if="tab === 'premios'"
      class="sorteo-detalle__premios"
    >
      <article
        v-for="premio in sorteo.premios"
        :key="premio.id"
        :class="['sorteo-premio', { 'sorteo-premio--ganado': premio.ganador }]"
      >
        <div class="sorteo-premio__puesto">
          {{ premio.orden }}.°
        </div>
        <div class="sorteo-premio__cuerpo">
          <div class="sorteo-premio__nombre">
            {{ premio.descripcion }}
          </div>
          <template v-if="premio.ganador">
            <div class="sorteo-premio__ganador">
              <q-icon name="emoji_events" />
              <span class="text-mono">{{ premio.ganador.codigo }}</span>
              {{ premio.ganador.cliente?.nombre }}
              <span class="sorteo-detalle__chico">
                {{ [premio.ganador.cliente?.tipo_documento, premio.ganador.cliente?.numero_documento].filter(Boolean).join(' ') }}
                <template v-if="premio.ganador.cliente?.telefono"> · {{ premio.ganador.cliente.telefono }}</template>
              </span>
            </div>
            <div class="sorteo-detalle__chico">
              Salió el {{ formatearFechaHora(premio.sorteado_at) }} entre {{ premio.participantes }} tickets
              <template v-if="premio.sorteado_por">
                · lo sorteó {{ premio.sorteado_por }}
              </template>
            </div>
          </template>
          <div
            v-else
            class="sorteo-detalle__chico"
          >
            Sin sortear
          </div>
          <ul
            v-if="premio.descartes.length"
            class="sorteo-premio__descartes"
          >
            <li
              v-for="(d, i) in premio.descartes"
              :key="i"
            >
              Descartado: #{{ String(d.numero).padStart(6, '0') }} {{ d.cliente }} — {{ d.motivo }}
              <span class="sorteo-detalle__chico">({{ d.user }}, {{ formatearFechaHora(d.at) }})</span>
            </li>
          </ul>
        </div>
        <AppButton
          v-if="premio.ganador"
          variant="tertiary"
          icon="mdi-whatsapp"
          label="Avisar"
          :href="enlaceWhatsappGanador(sorteo, premio)"
          target="_blank"
        />
      </article>

      <div
        v-if="sorteo.bases"
        class="sorteo-detalle__bases"
      >
        <strong>Bases y condiciones</strong>
        <p>{{ sorteo.bases }}</p>
      </div>
    </section>

    <!-- ── Participantes ── -->
    <section v-else-if="tab === 'participantes'">
      <AppFilterBar
        v-model:search="buscarParticipante"
        search-placeholder="Buscar por nombre o documento"
        :has-active-filters="Boolean(buscarParticipante)"
        :refreshing="cargandoParticipantes"
        @clear="buscarParticipante = ''"
        @refresh="participantesRef.requestServerInteraction()"
      />
      <AppTable
        ref="participantesRef"
        v-model:pagination="paginacionParticipantes"
        :rows="participantes"
        :columns="columnasParticipantes"
        row-key="cliente_id"
        :loading="cargandoParticipantes"
        :filter="filtroParticipantes"
        no-data-label="Todavía nadie tiene tickets: salen solos con cada venta u orden de servicio a un cliente registrado, o véndelos con “Vender tickets”."
        @request="pedirParticipantes"
      >
        <template #body-cell-nombre="props">
          <q-td :props="props">
            <div class="sorteo-detalle__fuerte">
              {{ props.row.nombre }}
            </div>
            <div class="sorteo-detalle__chico">
              {{ [props.row.tipo_documento, props.row.numero_documento].filter(Boolean).join(' ') }}
              <template v-if="props.row.telefono">
                · {{ props.row.telefono }}
              </template>
            </div>
          </q-td>
        </template>
        <template #body-cell-tickets="props">
          <q-td
            :props="props"
            class="text-mono"
          >
            {{ props.row.tickets }}
            <span class="sorteo-detalle__chico">({{ props.row.probabilidad }} %)</span>
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
              size="sm"
              icon="confirmation_number"
              color="grey-7"
              :aria-label="`Ver los tickets de ${props.row.nombre}`"
              @click="verTicketsDe(props.row)"
            >
              <q-tooltip>Ver sus tickets</q-tooltip>
            </q-btn>
            <q-btn
              flat
              dense
              round
              size="sm"
              icon="mdi-whatsapp"
              color="positive"
              :href="enlaceWhatsappParticipante(sorteo, props.row)"
              target="_blank"
              :aria-label="`Avisar por WhatsApp a ${props.row.nombre}`"
            >
              <q-tooltip>Avisarle cuántos tickets tiene</q-tooltip>
            </q-btn>
          </q-td>
        </template>
      </AppTable>
    </section>

    <!-- ── Tickets ── -->
    <section v-else>
      <AppFilterBar
        v-model:search="buscarTicket"
        search-placeholder="N° de ticket, cliente o documento"
        :has-active-filters="Boolean(buscarTicket || clienteTickets || conAnulados)"
        :refreshing="cargandoTickets"
        @clear="limpiarTickets"
        @refresh="ticketsRef.requestServerInteraction()"
      >
        <q-chip
          v-if="clienteTickets"
          removable
          dense
          @remove="clienteTickets = null"
        >
          {{ clienteTickets.nombre }}
        </q-chip>
        <q-toggle
          v-model="conAnulados"
          label="Incluir anulados"
          dense
        />
      </AppFilterBar>
      <AppTable
        ref="ticketsRef"
        v-model:pagination="paginacionTickets"
        :rows="tickets"
        :columns="columnasTickets"
        :loading="cargandoTickets"
        :filter="filtroTickets"
        no-data-label="No hay tickets que coincidan."
        @request="pedirTickets"
      >
        <template #body-cell-codigo="props">
          <q-td
            :props="props"
            :class="['text-mono', { 'sorteo-detalle__anulado': props.row.anulado }]"
          >
            {{ props.row.codigo }}
            <span
              v-if="props.row.anulado"
              class="sorteo-detalle__chico"
            >anulado</span>
          </q-td>
        </template>
      </AppTable>
    </section>

    <AppDialog
      v-model="ventaDialog"
      title="Vender tickets"
      persistent
    >
      <VenderTickets
        v-if="ventaDialog"
        ref="ventaRef"
        :sorteo="sorteo"
        @vendido="vendido"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="ventaDialog = false"
        />
        <AppButton
          variant="primary"
          icon="point_of_sale"
          label="Cobrar y entregar tickets"
          :loading="ventaRef?.enviando"
          @click="ventaRef.submit()"
        />
      </template>
    </AppDialog>
    <ImpresionTicket ref="impresionRef" />

    <AppDialog
      v-model="formDialog"
      title="Editar sorteo"
      size="lg"
      persistent
    >
      <SorteosForm
        :id="sorteo.id"
        ref="formRef"
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
  </div>

  <div
    v-else
    class="sorteo-detalle__cargando"
  >
    <q-spinner size="24px" />
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
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppStatTile from '@/components/AppStatTile.vue'
import AppTable from '@/components/AppTable.vue'
import SorteoService from '@/services/SorteoService'
import { useUserStore } from '@/stores/user-store'
import { formatearFechaHora } from '@/utils/fechas'
import { formatearPrecio } from '@/utils/moneda'
import { enlaceWhatsappGanador, enlaceWhatsappParticipante, ESTADOS, fechaCorta, reglaDe } from './constantes'
import SorteosForm from './SorteosForm.vue'
import VenderTickets from './VenderTickets.vue'
import ImpresionTicket from '@/modules/Ventas/ImpresionTicket.vue'

const props = defineProps({
  id: {
    type: Number,
    required: true
  }
})

const $q = useQuasar()
const router = useRouter()
const userStore = useUserStore()

const sorteo = ref(null)
const tab = ref('premios')

async function cargar () {
  sorteo.value = await SorteoService.get(props.id)
}
onMounted(cargar)

const aviso = computed(() => {
  const s = sorteo.value
  return {
    programado: { icono: 'schedule', texto: `Empieza el ${fechaCorta(s.inicia_el)}: desde ese día cada venta u orden de servicio a un cliente registrado le da 1 ticket.` },
    abierto: { icono: 'confirmation_number', texto: `Abierto: cada venta u orden de servicio de un cliente registrado le da 1 ticket (sale impreso en su ticket), y puedes vender más con “Vender tickets”. Se sortea desde el ${fechaCorta(s.termina_el)}.` },
    por_sortear: { icono: 'celebration', texto: 'Terminó el período: ya no entran tickets. Abre el sorteo en vivo para sacar los premios.' },
    sorteando: { icono: 'celebration', texto: 'Faltan premios por sortear. Ya no entran tickets nuevos.' },
    cancelado: { icono: 'block', texto: 'Cancelado: no entran tickets ni se sortea.' }
  }[s.estado] ?? null
})

const puedeCancelar = computed(() => userStore.hasPermission('sorteos.cancelar') && ['programado', 'abierto', 'por_sortear', 'sorteando'].includes(sorteo.value?.estado))
const puedeEliminar = computed(() => userStore.hasPermission('sorteos.destroy') && sorteo.value?.tickets_count === 0 && sorteo.value?.editable)

// ── Acciones ──
const exportando = ref(false)
async function exportar () {
  exportando.value = true
  try {
    await SorteoService.excel(sorteo.value)
  } finally {
    exportando.value = false
  }
}

// En otra pestaña: se pone en pantalla completa para la transmisión.
function abrirEnVivo () {
  window.open(router.resolve(`/sorteo-en-vivo/${sorteo.value.id}`).href, '_blank')
}

function cancelar () {
  $q.dialog({
    title: 'Cancelar sorteo',
    message: `¿Cancelar “${sorteo.value.nombre}”? No entran más tickets ni se sortea. Los tickets entregados quedan en el historial.`,
    cancel: { label: 'Volver', flat: true, noCaps: true },
    ok: { label: 'Cancelar sorteo', color: 'negative', noCaps: true, unelevated: true },
    persistent: true
  }).onOk(async () => {
    sorteo.value = await SorteoService.cancelar(sorteo.value.id)
  })
}

function eliminar () {
  $q.dialog({
    title: 'Eliminar sorteo',
    message: `¿Eliminar “${sorteo.value.nombre}”? Todavía no tiene tickets.`,
    cancel: { label: 'Volver', flat: true, noCaps: true },
    ok: { label: 'Eliminar', color: 'negative', noCaps: true, unelevated: true },
    persistent: true
  }).onOk(async () => {
    await SorteoService.delete(sorteo.value.id)
    router.replace('/sorteos')
  })
}

// ── Venta de tickets sueltos ──
const ventaDialog = ref(false)
const ventaRef = ref()
const impresionRef = ref()
async function vendido (venta) {
  ventaDialog.value = false
  const numeros = venta.sorteos?.find((s) => s.sorteo === sorteo.value.nombre)?.numeros ?? []
  await cargar()
  participantesRef.value?.requestServerInteraction?.()
  ticketsRef.value?.requestServerInteraction?.()
  $q.notify({
    type: 'positive',
    message: `${numeros.length === 1 ? 'Ticket vendido' : `${numeros.length} tickets vendidos`} a ${venta.cliente?.nombre ?? 'el cliente'}: ${numeros.join(', ')}`,
    position: 'top-right',
    timeout: 6000,
    actions: [{ label: 'Imprimir ticket', color: 'white', handler: () => impresionRef.value?.imprimir(venta) }]
  })
}

const formDialog = ref(false)
const formRef = ref()
async function guardado () {
  formDialog.value = false
  await cargar()
  $q.notify({ type: 'positive', message: 'Sorteo guardado.', position: 'top-right', timeout: 1500 })
}

// ── Participantes ──
const columnasParticipantes = [
  { name: 'nombre', label: 'Cliente', field: 'nombre', align: 'left' },
  { name: 'tickets', label: 'Tickets (chance)', field: 'tickets', align: 'left' },
  { name: 'acciones', label: '', field: 'cliente_id', align: 'right' }
]
const participantesRef = ref()
const participantes = ref([])
const cargandoParticipantes = ref(false)
const paginacionParticipantes = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })
const buscarParticipante = ref('')
const filtroParticipantes = ref('')
let timerParticipante
watch(buscarParticipante, (valor) => {
  clearTimeout(timerParticipante)
  timerParticipante = setTimeout(() => { filtroParticipantes.value = valor.trim() }, 400)
})

async function pedirParticipantes ({ pagination }) {
  cargandoParticipantes.value = true
  try {
    const { data, total } = await SorteoService.participantes(props.id, {
      params: { page: pagination.page, rowsPerPage: pagination.rowsPerPage, search: filtroParticipantes.value }
    })
    participantes.value = data
    paginacionParticipantes.value = { ...pagination, rowsNumber: total }
  } finally {
    cargandoParticipantes.value = false
  }
}

// ── Tickets ──
const columnasTickets = [
  { name: 'codigo', label: 'Ticket', field: 'codigo', align: 'left', sortable: true },
  { name: 'cliente', label: 'Cliente', field: (t) => t.cliente?.nombre, align: 'left' },
  { name: 'origen', label: 'Origen', field: (t) => (t.vendido ? 'Comprado' : 'Por compra / OS'), align: 'left' },
  { name: 'venta', label: 'Venta', field: (t) => t.pedido ? `${t.pedido.codigo} · ${formatearPrecio(t.pedido.total)}` : '—', align: 'left' },
  { name: 'sede', label: 'Sede', field: 'sede', align: 'left' },
  { name: 'fecha', label: 'Fecha', field: (t) => formatearFechaHora(t.fecha), align: 'left' }
]
const ticketsRef = ref()
const tickets = ref([])
const cargandoTickets = ref(false)
const paginacionTickets = ref({ sortBy: null, descending: false, page: 1, rowsPerPage: 20, rowsNumber: 0 })
const buscarTicket = ref('')
const busquedaTicket = ref('')
const clienteTickets = ref(null)
const conAnulados = ref(false)
let timerTicket
watch(buscarTicket, (valor) => {
  clearTimeout(timerTicket)
  timerTicket = setTimeout(() => { busquedaTicket.value = valor.trim() }, 400)
})
const filtroTickets = computed(() => JSON.stringify({ s: busquedaTicket.value, c: clienteTickets.value?.cliente_id, a: conAnulados.value }))

function limpiarTickets () {
  buscarTicket.value = ''
  clienteTickets.value = null
  conAnulados.value = false
}

function verTicketsDe (participante) {
  clienteTickets.value = participante
  buscarTicket.value = ''
  tab.value = 'tickets'
}

async function pedirTickets ({ pagination }) {
  cargandoTickets.value = true
  try {
    const params = {
      page: pagination.page,
      rowsPerPage: pagination.rowsPerPage,
      search: busquedaTicket.value,
      anulados: conAnulados.value ? 1 : 0
    }
    if (pagination.sortBy === 'codigo') params.order_by = pagination.descending ? '-numero' : 'numero'
    if (clienteTickets.value) params.cliente_id = clienteTickets.value.cliente_id
    const { data, total } = await SorteoService.tickets(props.id, { params })
    tickets.value = data
    paginacionTickets.value = { ...pagination, rowsNumber: total }
  } finally {
    cargandoTickets.value = false
  }
}

// La tabla de la pestaña se monta al entrar: entonces pide sus datos.
watch(tab, async (valor) => {
  await Promise.resolve()
  if (valor === 'participantes') participantesRef.value?.requestServerInteraction()
  if (valor === 'tickets') ticketsRef.value?.requestServerInteraction()
}, { flush: 'post' })
</script>

<style lang="scss" scoped>
.sorteo-detalle__aviso {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--app-brand-soft);
  font-size: 13px;
  color: var(--app-brand-soft-ink);
}

.sorteo-detalle__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.sorteo-detalle__tabs {
  border-bottom: 1px solid var(--app-border-subtle);
}

.sorteo-detalle__premios {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sorteo-premio {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 14px 16px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
  background: var(--app-surface);

  &--ganado {
    border-color: color-mix(in srgb, var(--q-positive) 40%, transparent);
  }
}

.sorteo-premio__puesto {
  min-width: 36px;
  font-size: 20px;
  font-weight: 800;
  color: var(--q-primary);
  font-variant-numeric: tabular-nums;
}

.sorteo-premio__cuerpo {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.sorteo-premio__nombre {
  font-weight: 600;
  color: var(--app-ink);
}

.sorteo-premio__ganador {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: var(--app-ink);
}

.sorteo-premio__descartes {
  margin: 4px 0 0;
  padding-left: 18px;
  font-size: 12px;
  color: var(--app-ink-2);
}

.sorteo-detalle__bases {
  padding: 14px 16px;
  border: 1px dashed var(--app-border-control);
  border-radius: 12px;
  font-size: 13px;
  color: var(--app-ink-2);

  p {
    margin: 6px 0 0;
    white-space: pre-line;
  }
}

.sorteo-detalle__fuerte {
  font-weight: 600;
  color: var(--app-ink);
}

.sorteo-detalle__chico {
  font-size: 12px;
  color: var(--app-ink-2);
}

.sorteo-detalle__anulado {
  text-decoration: line-through;
  color: var(--app-ink-2);
}

.sorteo-detalle__cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}
</style>
