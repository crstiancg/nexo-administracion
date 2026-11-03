<template>
  <div class="app-list-page">
    <AppPageHeader
      :title="saludo"
      :subtitle="`${datos?.sede?.nombre ?? userStore.sede?.nombre ?? ''} · ${hoyLargo}`"
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
            <h2 class="dash__titulo">
              Ventas de los últimos 14 días
            </h2>
            <button
              type="button"
              class="dash__link"
              @click="verTabla = !verTabla"
            >
              {{ verTabla ? 'Ver gráfico' : 'Ver tabla' }}
            </button>
          </div>
          <VentasPorDia
            v-if="!verTabla"
            :dias="datos.ventas.por_dia"
          />
          <table
            v-else
            class="dash__tabla"
          >
            <thead>
              <tr>
                <th>Día</th>
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
                v-for="d in [...datos.ventas.por_dia].reverse()"
                :key="d.fecha"
              >
                <td>{{ d.fecha.split('-').reverse().join('/') }}</td>
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
import PanelService from '@/services/PanelService'
import { useUserStore } from '@/stores/user-store'
import { formatearFechaHora } from '@/utils/fechas'
import { ETIQUETA_IGV } from '@/utils/igv'
import { formatearPrecio } from '@/utils/moneda'
import VentasPorDia from './VentasPorDia.vue'

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

async function cargar () {
  try {
    datos.value = await PanelService.dashboard()
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)
const reloj = setInterval(cargar, 120_000)
onBeforeUnmount(() => clearInterval(reloj))

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
  return [
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
  background: #B7860B;
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
