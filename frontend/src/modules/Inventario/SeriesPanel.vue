<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Números de serie"
      subtitle="Busca una serie: de qué compra entró, a quién se vendió y si sigue en garantía"
    />

    <q-input
      v-model="busqueda"
      dense
      outlined
      clearable
      autofocus
      placeholder="Escanea o escribe el número de serie"
      class="ser-buscador"
      @keydown.enter="buscar"
    >
      <template #prepend>
        <q-icon name="qr_code_scanner" />
      </template>
    </q-input>

    <div
      v-if="cargando"
      class="ser-vacio"
    >
      <q-spinner size="22px" />
    </div>

    <p
      v-else-if="buscado && !resultados.length"
      class="ser-vacio"
    >
      No hay ninguna serie que coincida con «{{ buscado }}».
    </p>

    <div
      v-else
      class="ser-resultados"
    >
      <button
        v-for="s in resultados"
        :key="s.id"
        type="button"
        :class="['ser-fila', { 'ser-fila--activa': detalle?.id === s.id }]"
        @click="abrir(s.id)"
      >
        <span class="text-mono ser-numero">{{ s.numero }}</span>
        <span class="ser-producto">{{ s.producto }} <span class="ser-muted">{{ s.presentacion }} · {{ s.sku }}</span></span>
        <AppChip
          :status="ESTADOS[s.estado].status"
          :label="s.estado === 'en_stock' && s.sede ? `En stock · ${s.sede}` : ESTADOS[s.estado].label"
        />
      </button>
    </div>

    <!-- ── La historia de la serie ── -->
    <AppDialog
      v-model="detalleDialog"
      :title="detalle ? `Serie ${detalle.numero}` : 'Serie'"
      size="md"
    >
      <div
        v-if="detalle"
        class="ser-detalle"
      >
        <div class="ser-cabecera">
          <div>
            <div class="ser-detalle__producto">
              {{ detalle.producto }}
            </div>
            <div class="ser-muted">
              {{ detalle.presentacion }} · {{ detalle.sku }}
            </div>
          </div>
          <AppChip
            :status="ESTADOS[detalle.estado].status"
            :label="ESTADOS[detalle.estado].label"
          />
        </div>

        <!-- Lo que preguntan en el mostrador: a quién y si tiene garantía. -->
        <div
          v-if="detalle.venta"
          :class="['ser-garantia', detalle.en_garantia ? 'ser-garantia--si' : 'ser-garantia--no']"
        >
          <q-icon
            :name="detalle.en_garantia ? 'verified_user' : 'report_problem'"
            size="22px"
          />
          <div>
            <strong v-if="detalle.garantia_hasta">
              {{ detalle.en_garantia ? 'En garantía' : 'Garantía vencida' }} · hasta el {{ fechaCorta(detalle.garantia_hasta) }}
            </strong>
            <strong v-else>Sin garantía registrada para este producto</strong>
            <div>
              Vendida el {{ formatearFechaHora(detalle.venta.fecha) }} a
              <strong>{{ detalle.venta.cliente?.nombre ?? 'Cliente varios' }}</strong>
              <template v-if="detalle.venta.pedido">
                ·
                <router-link :to="`/pedidos?ver=${detalle.venta.pedido_id}`">
                  {{ detalle.venta.pedido }}
                </router-link>
              </template>
            </div>
          </div>
        </div>

        <h3 class="ser-subtitulo">
          Historia
        </h3>
        <AppTimeline :items="pasos" />
      </div>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/boot/axios'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTimeline from '@/components/AppTimeline.vue'
import { fechaCorta } from '@/modules/Cotizaciones/constantes'
import { formatearFechaHora } from '@/utils/fechas'

const ESTADOS = {
  en_stock: { label: 'En stock', status: 'info' },
  vendida: { label: 'Vendida', status: 'positive' },
  baja: { label: 'Dada de baja', status: 'negative' }
}

const MOTIVOS = {
  compra: 'Compra',
  venta: 'Venta',
  devolucion_venta: 'Devolución de venta',
  anulacion_compra: 'Anulación de compra',
  traslado_salida: 'Traslado (salió)',
  traslado_entrada: 'Traslado (llegó)',
  conteo: 'Ajuste por conteo',
  merma: 'Merma',
  danado: 'Dañado',
  regalo: 'Regalo / promoción',
  devolucion_proveedor: 'Devolución al proveedor',
  uso_interno: 'Uso interno'
}

const route = useRoute()
const busqueda = ref(route.query.numero ?? '')
const buscado = ref('')
const resultados = ref([])
const cargando = ref(false)
const detalle = ref(null)
const detalleDialog = ref(false)

async function buscar () {
  const texto = (busqueda.value ?? '').trim()
  if (texto.length < 2) return
  cargando.value = true
  try {
    resultados.value = (await api.get('api/series', { params: { search: texto } })).data.data
    buscado.value = texto
    // Una sola (lo normal al escanear): se abre directo.
    if (resultados.value.length === 1) abrir(resultados.value[0].id)
  } finally {
    cargando.value = false
  }
}

async function abrir (id) {
  detalle.value = (await api.get(`api/series/${id}`)).data
  detalleDialog.value = true
}

const pasos = computed(() => (detalle.value?.historia ?? []).map((h, n) => ({
  key: n,
  titulo: `${MOTIVOS[h.motivo] ?? (h.entra ? 'Entrada' : 'Salida')}${h.referencia ? ` · ${h.referencia}` : ''}`,
  fecha: formatearFechaHora(h.fecha),
  descripcion: [h.proveedor && `Proveedor: ${h.proveedor}`, h.cliente && `Cliente: ${h.cliente}`, h.sede, h.usuario].filter(Boolean).join(' · '),
  icon: h.entra ? 'move_to_inbox' : 'sell',
  color: h.entra ? 'primary' : 'positive',
  estado: 'hecho'
})))

onMounted(() => { if (busqueda.value) buscar() })
</script>

<style lang="scss" scoped>
.ser-buscador {
  max-width: 520px;
  margin-bottom: 16px;
}

.ser-vacio {
  display: flex;
  justify-content: center;
  padding: 32px 16px;
  color: var(--app-ink-2);
}

.ser-resultados {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 860px;
}

.ser-fila {
  display: grid;
  grid-template-columns: minmax(140px, auto) 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
  background: var(--app-surface);
  color: var(--app-ink);
  font: inherit;
  text-align: left;
  cursor: pointer;

  &:hover,
  &--activa {
    border-color: var(--q-primary);
  }
}

.ser-numero {
  font-weight: 700;
}

.ser-producto {
  min-width: 0;
  font-size: 13.5px;
}

.ser-muted {
  font-size: 12px;
  color: var(--app-ink-2);
}

.ser-detalle {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.ser-cabecera {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.ser-detalle__producto {
  font-size: 16px;
  font-weight: 700;
}

.ser-garantia {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 12px;
  font-size: 13px;
  line-height: 1.5;

  a {
    color: inherit;
  }
}

.ser-garantia--si {
  background: color-mix(in srgb, var(--q-positive) 14%, transparent);

  .q-icon {
    color: var(--q-positive);
  }
}

.ser-garantia--no {
  background: color-mix(in srgb, var(--q-warning) 14%, transparent);

  .q-icon {
    color: var(--q-warning);
  }
}

.ser-subtitulo {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
}
</style>
