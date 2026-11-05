<template>
  <div class="app-list-page">
    <AppPageHeader
      :title="saludo"
      :subtitle="`${datos?.sede?.nombre ?? userStore.sede?.nombre ?? ''} · ${hoyLargo}${actualizado ? ` · actualizado ${actualizado}` : ''}`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('reportes.ventas')"
          variant="tertiary"
          label="Reportes"
          icon="insights"
          to="/reportes"
        />
        <AppButton
          v-if="userStore.hasPermission('ventas.store')"
          variant="primary"
          label="Punto de venta"
          icon="point_of_sale"
          to="/pos"
        />
      </template>
    </AppPageHeader>

    <div
      v-if="cargando && !datos"
      class="dash__cargando"
    >
      <q-spinner size="28px" />
    </div>

    <template v-else-if="datos">
      <!-- ── Lo urgente arriba ── -->
      <div
        v-if="urgentes.length"
        class="dash__alertas"
      >
        <router-link
          v-for="alerta in urgentes"
          :key="alerta.clave"
          :to="alerta.to"
          :class="['dash__alerta', `dash__alerta--${alerta.nivel}`]"
        >
          <q-icon
            :name="alerta.nivel === 'critical' ? 'error' : 'warning'"
            size="20px"
          />
          <span>
            <strong>{{ alerta.titulo }}</strong>
            <span class="dash__alertaDetalle">{{ alerta.detalle }}</span>
          </span>
          <q-icon
            name="chevron_right"
            size="18px"
            class="dash__alertaIr"
          />
        </router-link>
      </div>

      <!-- ── Indicadores del día ── -->
      <div
        v-if="datos.ventas"
        class="dash__tiles"
      >
        <AppStatTile
          featured
          label="Ventas de hoy"
          :value="formatearPrecio(datos.ventas.hoy.total)"
          :delta="variacion.texto"
          :trend="variacion.sube ? 'up' : 'down'"
          :trend-is-good="variacion.sube"
          delta-caption="vs. ayer"
        />
        <AppStatTile
          label="Ventas registradas hoy"
          :value="String(datos.ventas.hoy.ventas)"
        />
        <AppStatTile
          label="Ventas del mes"
          :value="formatearPrecio(datos.ventas.mes.total)"
          :delta="variacionMes.texto"
          :trend="variacionMes.sube ? 'up' : 'down'"
          :trend-is-good="variacionMes.sube"
          delta-caption="vs. mes anterior"
        />
        <AppStatTile
          :label="`${ETIQUETA_IGV} del mes`"
          :value="formatearPrecio(datos.ventas.mes.igv)"
        />
      </div>

      <!-- ── Gráfico + caja ── -->
      <div class="dash__fila dash__fila--principal">
        <AppCard
          v-if="datos.ventas"
          class="dash__card"
        >
          <div class="dash__cardCabecera">
            <div>
              <h2 class="dash__titulo">
                {{ periodo === '14d' ? 'Ventas de los últimos 14 días' : 'Ventas por mes' }}
              </h2>
              <p class="dash__muted">
                {{ periodo === '14d' ? 'Hoy destacado' : 'Este mes destacado' }} ·
                <button
                  type="button"
                  class="dash__link"
                  @click="verTabla = !verTabla"
                >
                  {{ verTabla ? 'Ver gráfico' : 'Ver tabla' }}
                </button>
              </p>
            </div>
            <!-- Período: días o meses (6M / 12M / todo lo que hay). -->
            <div
              class="dash__periodos"
              role="radiogroup"
              aria-label="Período del gráfico"
            >
              <button
                v-for="p in PERIODOS"
                :key="p.value"
                type="button"
                role="radio"
                :aria-checked="String(periodo === p.value)"
                :class="['dash__periodo', { 'dash__periodo--activo': periodo === p.value }]"
                @click="periodo = p.value"
              >
                {{ p.label }}
              </button>
            </div>
          </div>
          <VentasPorDia
            v-if="!verTabla"
            :key="periodo"
            :dias="serie"
            :por-mes="periodo !== '14d'"
          />
          <table
            v-else
            class="dash__tabla"
          >
            <thead>
              <tr>
                <th>{{ periodo === '14d' ? 'Día' : 'Mes' }}</th>
                <th class="text-right">
                  Ventas
                </th>
                <th class="text-right">
                  Total
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="d in [...serie].reverse()"
                :key="d.fecha"
              >
                <td>{{ periodo === '14d' ? d.fecha.split('-').reverse().join('/') : d.fecha.slice(0, 7).split('-').reverse().join('/') }}</td>
                <td class="text-right text-mono">
                  {{ d.ventas }}
                </td>
                <td class="text-right text-mono">
                  {{ formatearPrecio(d.total) }}
                </td>
              </tr>
            </tbody>
          </table>
        </AppCard>

        <AppCard
          v-if="datos.caja"
          :class="['dash__card', 'dash__caja', `dash__caja--${datos.caja.estado}`]"
        >
          <div class="dash__cardCabecera">
            <h2 class="dash__titulo">
              Caja
            </h2>
            <AppChip
              :status="ESTADOS_CAJA[datos.caja.estado].status"
              :label="ESTADOS_CAJA[datos.caja.estado].label"
            />
          </div>
          <template v-if="datos.caja.estado !== 'cerrada'">
            <p class="dash__muted">
              Abierta {{ formatearFechaHora(datos.caja.abierta_at) }}
            </p>
            <dl class="dash__datos">
              <dt>Cobrado</dt>
              <dd class="text-mono">
                {{ formatearPrecio(datos.caja.total_cobrado) }}
              </dd>
              <dt>Efectivo en cajón</dt>
              <dd class="text-mono">
                {{ formatearPrecio(datos.caja.efectivo_esperado) }}
              </dd>
            </dl>
          </template>
          <p
            v-else
            class="dash__muted"
          >
            Se abre cada día desde el punto de venta, con el efectivo inicial del cajón.
          </p>
          <AppButton
            v-if="userStore.hasPermission('ventas.store')"
            :variant="datos.caja.estado === 'abierta' ? 'secondary' : 'primary'"
            :label="datos.caja.estado === 'cerrada' ? 'Abrir en el POS' : datos.caja.estado === 'vencida' ? 'Cerrar en el POS' : 'Ir al POS'"
            icon="point_of_sale"
            to="/pos"
            class="dash__cajaBoton"
          />
        </AppCard>
      </div>

      <!-- ── Pendientes e inventario ── -->
      <div
        v-if="atajos.length"
        class="dash__atajos"
      >
        <router-link
          v-for="a in atajos"
          :key="a.label"
          :to="a.to"
          :class="['dash__atajo', { 'dash__atajo--atencion': a.atencion && a.valor > 0 }]"
        >
          <q-icon
            :name="a.icono"
            size="22px"
            class="dash__atajoIcono"
          />
          <span class="dash__atajoValor text-mono">{{ a.valor }}</span>
          <span class="dash__atajoLabel">{{ a.label }}</span>
        </router-link>
      </div>

      <!-- ── Canales del mes + taller ── -->
      <div
        v-if="datos.ventas || datos.taller"
        class="dash__fila"
      >
        <AppCard
          v-if="datos.ventas"
          class="dash__card"
        >
          <div>
            <h2 class="dash__titulo">
              De dónde vienen las ventas
            </h2>
            <p class="dash__muted">
              Participación en las ventas de {{ mesActual }}
            </p>
          </div>
          <p
            v-if="!datos.ventas.por_canal.length"
            class="dash__muted"
          >
            Todavía no hay ventas este mes.
          </p>
          <ul
            v-else
            class="dash__canales"
          >
            <li
              v-for="c in datos.ventas.por_canal"
              :key="c.canal"
            >
              <span class="dash__canalNombre">
                <q-icon
                  :name="ICONOS_CANAL[c.canal] ?? 'sell'"
                  size="16px"
                />
                {{ c.label }}
              </span>
              <span class="dash__canalPct text-mono">{{ c.porcentaje }}%</span>
              <span class="dash__canalBarra">
                <span :style="{ width: `${c.porcentaje}%` }" />
              </span>
              <span class="dash__muted dash__canalDetalle">
                {{ c.ventas }} {{ c.ventas === 1 ? 'venta' : 'ventas' }} · {{ formatearPrecio(c.total) }}
              </span>
            </li>
          </ul>
        </AppCard>

        <AppCard
          v-if="datos.taller"
          class="dash__card"
        >
          <div class="dash__cardCabecera">
            <div>
              <h2 class="dash__titulo">
                Taller
              </h2>
              <p class="dash__muted">
                <template v-if="datos.taller.promedio_dias !== null">
                  Un equipo sale en {{ datos.taller.promedio_dias }} {{ datos.taller.promedio_dias === 1 ? 'día' : 'días' }} en promedio
                </template>
                <template v-else>
                  Sin entregas en los últimos 30 días
                </template>
              </p>
            </div>
            <router-link
              to="/ordenes-servicio"
              class="dash__link"
            >
              Ver órdenes
            </router-link>
          </div>
          <dl class="dash__datos">
            <dt>Recibidos hoy</dt>
            <dd class="text-mono">
              {{ datos.taller.recibidas_hoy }}
            </dd>
            <dt>Entregados este mes</dt>
            <dd class="text-mono">
              {{ datos.taller.entregadas_mes }}
            </dd>
          </dl>
          <div v-if="datos.taller.por_tecnico.length">
            <h3 class="dash__subtitulo">
              Equipos a cargo de cada técnico
            </h3>
            <ul class="dash__canales">
              <li
                v-for="t in datos.taller.por_tecnico"
                :key="t.tecnico"
              >
                <span class="dash__canalNombre">
                  <q-icon
                    name="engineering"
                    size="16px"
                  />
                  {{ t.tecnico }}
                </span>
                <span class="dash__canalPct text-mono">{{ t.total }}</span>
                <span class="dash__canalBarra">
                  <span :style="{ width: `${(t.total / datos.taller.por_tecnico[0].total) * 100}%` }" />
                </span>
              </li>
            </ul>
          </div>
        </AppCard>
      </div>

      <!-- ── Cotizaciones: conversión del mes + por vencer ── -->
      <div
        v-if="datos.cotizaciones"
        class="dash__fila"
      >
        <AppCard class="dash__card">
          <div class="dash__cardCabecera">
            <div>
              <h2 class="dash__titulo">
                Cotizaciones de {{ mesActual }}
              </h2>
              <p class="dash__muted">
                <template v-if="datos.cotizaciones.mes.emitidas">
                  {{ datos.cotizaciones.mes.tasa }}% se convirtió en venta u orden de servicio
                </template>
                <template v-else>
                  Todavía no hay cotizaciones este mes
                </template>
              </p>
            </div>
            <router-link
              to="/cotizaciones"
              class="dash__link"
            >
              Ver cotizaciones
            </router-link>
          </div>
          <dl class="dash__datos">
            <dt>Emitidas</dt>
            <dd class="text-mono">
              {{ datos.cotizaciones.mes.emitidas }}
            </dd>
            <dt>Convertidas</dt>
            <dd class="text-mono">
              {{ datos.cotizaciones.mes.convertidas }} · {{ formatearPrecio(datos.cotizaciones.mes.monto) }}
            </dd>
          </dl>
          <div v-if="datos.cotizaciones.por_vendedor.length">
            <h3 class="dash__subtitulo">
              Conversión por vendedor
            </h3>
            <ul class="dash__canales">
              <li
                v-for="v in datos.cotizaciones.por_vendedor"
                :key="v.vendedor"
              >
                <span class="dash__canalNombre">
                  <q-icon
                    name="person"
                    size="16px"
                  />
                  {{ v.vendedor }}
                </span>
                <span class="dash__canalPct text-mono">{{ v.tasa }}%</span>
                <span class="dash__canalBarra">
                  <span :style="{ width: `${v.tasa}%` }" />
                </span>
                <span class="dash__muted dash__canalDetalle">
                  {{ v.convertidas }} de {{ v.emitidas }} · {{ formatearPrecio(v.monto) }}
                </span>
              </li>
            </ul>
          </div>
        </AppCard>

        <AppCard class="dash__card">
          <div class="dash__cardCabecera">
            <div>
              <h2 class="dash__titulo">
                Por vencer
              </h2>
              <p class="dash__muted">
                Vencen hoy o en los próximos días: llama o escribe al cliente a tiempo
              </p>
            </div>
            <router-link
              v-if="datos.cotizaciones.por_vencer_total"
              to="/cotizaciones?estado=por_vencer"
              class="dash__link"
            >
              Ver las {{ datos.cotizaciones.por_vencer_total }}
            </router-link>
          </div>
          <p
            v-if="!datos.cotizaciones.por_vencer.length"
            class="dash__muted"
          >
            Ninguna cotización vence en estos días.
          </p>
          <div
            v-for="c in datos.cotizaciones.por_vencer"
            :key="c.id"
            class="dash__cotizacion"
          >
            <router-link
              :to="`/cotizaciones?ver=${c.id}`"
              class="dash__venta"
            >
              <span>
                <strong>{{ c.codigo }}</strong>
                <span class="dash__muted">
                  {{ c.cliente?.nombre ?? 'Sin cliente' }} · vence {{ venceTexto(c.valida_hasta) }}
                  <template v-if="c.vistas"> · <q-icon name="visibility" /> {{ c.vistas }}</template>
                </span>
              </span>
              <span class="text-mono">{{ formatearPrecio(c.total) }}</span>
            </router-link>
            <q-btn
              v-if="numeroWhatsapp(c.cliente?.telefono)"
              flat
              dense
              round
              icon="mdi-whatsapp"
              size="sm"
              color="positive"
              :href="enlaceWhatsapp(c)"
              target="_blank"
              rel="noopener"
              :aria-label="`Escribir por WhatsApp a ${c.cliente.nombre} por ${c.codigo}`"
            />
          </div>
        </AppCard>
      </div>

      <!-- ── Top del mes + últimas ventas ── -->
      <div class="dash__fila">
        <AppCard
          v-if="datos.top_productos"
          class="dash__card"
        >
          <h2 class="dash__titulo">
            Lo más vendido del mes
          </h2>
          <p
            v-if="!datos.top_productos.length"
            class="dash__muted"
          >
            Todavía no hay ventas este mes.
          </p>
          <ol
            v-else
            class="dash__top"
          >
            <li
              v-for="(p, i) in datos.top_productos"
              :key="`${p.producto_id}-${p.presentacion}`"
            >
              <span class="dash__topPos">{{ i + 1 }}</span>
              <span class="dash__topNombre">
                {{ p.nombre }}
                <span class="dash__muted">{{ p.presentacion }} · {{ p.cantidad }} {{ p.unidad }}</span>
              </span>
              <span class="text-mono dash__topImporte">{{ formatearPrecio(p.importe) }}</span>
              <!-- Proporción contra el primero: se lee el ranking de un vistazo. -->
              <span
                class="dash__topBarra"
                :style="{ width: `${(p.importe / datos.top_productos[0].importe) * 100}%` }"
              />
            </li>
          </ol>
        </AppCard>

        <AppCard
          v-if="datos.ultimas_ventas"
          class="dash__card"
        >
          <div class="dash__cardCabecera">
            <h2 class="dash__titulo">
              Últimas ventas
            </h2>
            <router-link
              to="/pedidos"
              class="dash__link"
            >
              Ver pedidos
            </router-link>
          </div>
          <p
            v-if="!datos.ultimas_ventas.length"
            class="dash__muted"
          >
            Todavía no hay ventas.
          </p>
          <router-link
            v-for="v in datos.ultimas_ventas"
            :key="v.id"
            :to="`/pedidos?ver=${v.id}`"
            class="dash__venta"
          >
            <span>
              <strong>{{ v.codigo }}</strong>
              <span class="dash__muted">{{ v.cliente }} · {{ v.canal }}</span>
            </span>
            <span class="dash__ventaDerecha">
              <span class="text-mono">{{ formatearPrecio(v.total) }}</span>
              <span class="dash__muted">{{ formatearFechaHora(v.fecha) }}</span>
            </span>
          </router-link>
        </AppCard>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppChip from '@/components/AppChip.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppStatTile from '@/components/AppStatTile.vue'
import { enlaceWhatsapp, hoyLocal, numeroWhatsapp } from '@/modules/Cotizaciones/constantes'
import PanelService from '@/services/PanelService'
import { useUserStore } from '@/stores/user-store'
import { formatearFechaHora } from '@/utils/fechas'
import { ETIQUETA_IGV } from '@/utils/igv'
import { formatearPrecio } from '@/utils/moneda'
import VentasPorDia from './VentasPorDia.vue'

/** "hoy", "mañana" o "el 12/11". */
function venceTexto (iso) {
  if (iso === hoyLocal()) return 'hoy'
  if (iso === hoyLocal(1)) return 'mañana'
  return `el ${iso.slice(8, 10)}/${iso.slice(5, 7)}`
}

/**
 * Inicio: cómo va el día en la sede del usuario. Cada bloque llega sólo si
 * el usuario puede ver su pantalla (null = no se muestra). Se refresca solo
 * cada 2 minutos.
 */
const userStore = useUserStore()
const datos = ref(null)
const cargando = ref(true)
const verTabla = ref(false)

const ESTADOS_CAJA = {
  abierta: { status: 'positive', label: 'Abierta' },
  cerrada: { status: 'warning', label: 'Cerrada' },
  vencida: { status: 'negative', label: 'De otro día' }
}

// "hace 3 min": cuándo se trajeron los números (se refrescan solos).
const cargadoEn = ref(null)
const ahora = ref(Date.now())

async function cargar () {
  try {
    datos.value = await PanelService.dashboard()
    cargadoEn.value = Date.now()
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)
const reloj = setInterval(cargar, 120_000)
const tic = setInterval(() => { ahora.value = Date.now() }, 30_000)
onBeforeUnmount(() => {
  clearInterval(reloj)
  clearInterval(tic)
})

const actualizado = computed(() => {
  if (!cargadoEn.value) return ''
  const min = Math.floor((ahora.value - cargadoEn.value) / 60_000)
  return min < 1 ? 'recién' : `hace ${min} min`
})

// ── Gráfico: 14 días o meses ──
const PERIODOS = [
  { value: '14d', label: '14D' },
  { value: '6m', label: '6M' },
  { value: '12m', label: '12M' },
  { value: 'todo', label: 'Todo' }
]
const periodo = ref('14d')

const serie = computed(() => {
  const v = datos.value?.ventas
  if (!v) return []
  if (periodo.value === '14d') return v.por_dia
  if (periodo.value === '6m') return v.por_mes.slice(-6)
  if (periodo.value === '12m') return v.por_mes.slice(-12)
  return v.por_mes
})

const mesActual = (() => new Intl.DateTimeFormat('es-PE', { month: 'long' }).format(new Date()))()

const ICONOS_CANAL = {
  mostrador: 'storefront',
  whatsapp: 'mdi-whatsapp',
  redes: 'share',
  web: 'language',
  cotizacion: 'request_quote',
  servicio: 'build'
}

const saludo = computed(() => {
  const hora = new Date().getHours()
  const nombre = (userStore.name ?? '').split(' ')[0]
  const momento = hora < 12 ? 'Buenos días' : hora < 19 ? 'Buenas tardes' : 'Buenas noches'
  return nombre ? `${momento}, ${nombre}` : momento
})

const hoyLargo = (() => {
  const texto = new Intl.DateTimeFormat('es-PE', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date())
  return texto.charAt(0).toUpperCase() + texto.slice(1)
})()

// El mes hasta hoy contra el mes anterior hasta el mismo día.
const variacionMes = computed(() => {
  const mes = datos.value?.ventas?.mes.total ?? 0
  const anterior = datos.value?.ventas?.mes_anterior?.total ?? 0
  if (!anterior) return { texto: '', sube: true }
  const pct = Math.round(((mes - anterior) / anterior) * 100)
  return { texto: `${pct > 0 ? '+' : ''}${pct}%`, sube: pct >= 0 }
})

// Lo que bloquea o urge; lo de rutina queda en los atajos y la campana.
const urgentes = computed(() => (datos.value?.alertas ?? []).filter((a) => a.nivel !== 'info'))

const variacion = computed(() => {
  const hoy = datos.value?.ventas?.hoy.total ?? 0
  const ayer = datos.value?.ventas?.ayer.total ?? 0
  if (!ayer) return { texto: '', sube: true }
  const pct = Math.round(((hoy - ayer) / ayer) * 100)
  return { texto: `${pct > 0 ? '+' : ''}${pct}%`, sube: pct >= 0 }
})

// Cada atajo sólo si su número llegó (permiso).
const atajos = computed(() => {
  const p = datos.value?.pendientes ?? {}
  const inv = datos.value?.inventario ?? {}
  const t = datos.value?.taller ?? {}
  return [
    { label: 'Equipos en el taller', valor: t.en_taller, icono: 'build_circle', to: '/ordenes-servicio', atencion: false },
    { label: 'Listos para entregar', valor: t.listas, icono: 'task_alt', to: '/ordenes-servicio', atencion: true },
    { label: 'Más de 7 días en el taller', valor: t.atrasadas, icono: 'hourglass_bottom', to: '/ordenes-servicio', atencion: true },
    { label: 'Pedidos pendientes', valor: p.pedidos_pendientes, icono: 'pending_actions', to: '/pedidos', atencion: false },
    { label: 'Por entregar', valor: p.por_entregar, icono: 'local_shipping', to: '/pedidos', atencion: false },
    { label: 'Cotizaciones vigentes', valor: p.cotizaciones, icono: 'request_quote', to: '/cotizaciones', atencion: false },
    { label: 'Bajo el mínimo', valor: inv.bajo_minimo, icono: 'production_quantity_limits', to: '/reponer', atencion: true },
    { label: 'Lotes vencidos', valor: inv.lotes_vencidos, icono: 'event_busy', to: '/vencimientos', atencion: true },
    { label: 'Lotes por vencer', valor: inv.lotes_por_vencer, icono: 'schedule', to: '/vencimientos', atencion: true }
  ].filter((a) => a.valor !== null && a.valor !== undefined)
})
</script>

<style lang="scss" scoped>
.dash__cotizacion {
  display: flex;
  align-items: center;
  gap: 6px;

  .dash__venta {
    flex: 1;
    min-width: 0;
  }
}

.dash__cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}

.dash__alertas {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.dash__alerta {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border: 1px solid;
  border-radius: 12px;
  font-size: 14px;
  color: var(--app-ink);
  text-decoration: none;

  > span {
    display: flex;
    flex: 1;
    flex-wrap: wrap;
    gap: 4px 10px;
  }

  &--critical {
    border-color: var(--app-negative-border);
    background: var(--app-negative-soft);

    > .q-icon:first-child {
      color: var(--q-negative);
    }
  }

  &--warning {
    border-color: rgba($warning, 0.45);
    background: rgba($warning, 0.12);

    > .q-icon:first-child {
      color: var(--q-warning);
    }
  }
}

.dash__alertaDetalle {
  color: var(--app-ink-2);
}

.dash__alertaIr {
  color: var(--app-ink-2);
}

.dash__tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
}

.dash__fila {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 14px;

  &--principal {
    grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
  }
}

.dash__card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
  padding: 18px 20px;
}

.dash__cardCabecera {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.dash__periodos {
  display: inline-flex;
  flex-shrink: 0;
  padding: 3px;
  border: 1px solid var(--app-border-control);
  border-radius: 9px;
  background: var(--app-page);
}

.dash__periodo {
  padding: 4px 10px;
  border: 0;
  border-radius: 6px;
  background: none;
  font-size: 12px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &--activo {
    background: var(--app-surface);
    box-shadow: var(--app-shadow-card);
    color: var(--app-ink);
  }

  &:focus-visible {
    outline: 2px solid var(--q-primary);
    outline-offset: 1px;
  }
}

.dash__subtitulo {
  margin: 4px 0 8px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink-2);
}

.dash__canales {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;

  li {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 5px 12px;
    align-items: center;
  }
}

.dash__canalNombre {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--app-ink);
}

.dash__canalPct {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.dash__canalBarra {
  grid-column: 1 / -1;
  height: 6px;
  border-radius: 3px;
  background: var(--app-brand-soft);
  overflow: hidden;

  span {
    display: block;
    height: 100%;
    border-radius: 3px;
    background: var(--q-primary);
  }
}

.dash__canalDetalle {
  grid-column: 1 / -1;
  font-size: 12px;
}

.dash__titulo {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  line-height: 1.3;
  color: var(--app-ink);
}

.dash__link {
  padding: 0;
  border: 0;
  background: none;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
  text-decoration: underline;
  cursor: pointer;
}

.dash__muted {
  margin: 0;
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.dash__tabla {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;

  th,
  td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--app-border-subtle);
    text-align: left;
  }

  th {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--app-ink-2);
  }

  .text-right {
    text-align: right;
  }
}

.dash__datos {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 6px 12px;
  margin: 0;
  font-size: 14px;

  dt {
    color: var(--app-ink-2);
  }

  dd {
    margin: 0;
    font-weight: 700;
    text-align: right;
    color: var(--app-ink);
  }
}

.dash__cajaBoton {
  margin-top: auto;
}

.dash__atajos {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
}

.dash__atajo {
  display: grid;
  grid-template-columns: auto 1fr;
  grid-template-rows: auto auto;
  align-items: center;
  gap: 2px 10px;
  padding: 14px 16px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
  background: var(--app-surface);
  color: var(--app-ink);
  text-decoration: none;
  transition: border-color 0.15s ease;

  &:hover {
    border-color: var(--app-border-control-hover);
  }

  &--atencion {
    border-color: rgba($warning, 0.55);

    .dash__atajoIcono {
      color: var(--q-warning);
    }
  }
}

.dash__atajoIcono {
  grid-row: span 2;
  color: var(--app-ink-2);
}

.dash__atajoValor {
  font-size: 22px;
  font-weight: 700;
  line-height: 1.1;
}

.dash__atajoLabel {
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.dash__top {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;

  li {
    position: relative;
    display: grid;
    grid-template-columns: 22px minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    padding-bottom: 8px;
  }
}

.dash__topPos {
  font-size: 13px;
  font-weight: 700;
  color: var(--app-ink-2);
}

.dash__topNombre {
  display: flex;
  flex-direction: column;
  min-width: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--app-ink);
}

.dash__topImporte {
  font-size: 14px;
  font-weight: 700;
  color: var(--app-ink);
}

.dash__topBarra {
  position: absolute;
  bottom: 0;
  left: 32px;
  max-width: calc(100% - 32px);
  height: 3px;
  border-radius: 2px;
  background: var(--q-primary);
  opacity: 0.6;
}

.dash__venta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px solid var(--app-border-subtle);
  font-size: 14px;
  color: var(--app-ink);
  text-decoration: none;

  &:last-child {
    border-bottom: 0;
  }

  > span {
    display: flex;
    flex-direction: column;
    min-width: 0;
  }

  &:hover strong {
    text-decoration: underline;
  }
}

.dash__ventaDerecha {
  align-items: flex-end;
  font-weight: 700;
}

@media (max-width: 900px) {
  .dash__fila--principal {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
