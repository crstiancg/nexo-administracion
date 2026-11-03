<template>
  <div class="app-list-page reportes">
    <AppPageHeader
      title="Reportes"
      :subtitle="`Ventas confirmadas del ${fechaCorta(desde)} al ${fechaCorta(hasta)}`"
    />

    <!-- ── Filtros ── -->
    <div class="reportes__filtros">
      <q-btn-toggle
        v-model="rango"
        :options="RANGOS"
        no-caps
        unelevated
        dense
        toggle-color="primary"
        class="reportes__rangos"
      />
      <q-input
        v-model="desde"
        type="date"
        label="Desde"
        dense
        outlined
        class="reportes__fecha"
        @update:model-value="rango = null"
      />
      <q-input
        v-model="hasta"
        type="date"
        label="Hasta"
        dense
        outlined
        class="reportes__fecha"
        @update:model-value="rango = null"
      />
      <q-select
        v-if="sedes.length > 1"
        v-model="sedeId"
        :options="sedeOptions"
        label="Sede"
        dense
        outlined
        emit-value
        map-options
        class="reportes__sede"
      />
    </div>

    <q-linear-progress
      v-if="cargando"
      indeterminate
      color="primary"
    />

    <template v-if="ventas">
      <!-- ── Indicadores ── -->
      <div class="reportes__tiles">
        <AppStatTile
          featured
          label="Ventas"
          :value="formatearPrecio(ventas.resumen.total)"
        />
        <AppStatTile
          label="Cantidad de ventas"
          :value="String(ventas.resumen.ventas)"
        />
        <AppStatTile
          label="IGV de las ventas"
          :value="formatearPrecio(ventas.resumen.igv)"
        />
        <AppStatTile
          label="Ticket promedio"
          :value="formatearPrecio(ventas.resumen.ticket_promedio)"
        />
        <AppStatTile
          label="Ganancia"
          :value="formatearPrecio(ventas.resumen.ganancia)"
          :delta="ventas.resumen.margen !== null ? `${ventas.resumen.margen}%` : ''"
          :trend="ventas.resumen.ganancia < 0 ? 'down' : 'up'"
          :trend-is-good="ventas.resumen.ganancia >= 0"
          delta-caption="de margen"
        />
      </div>
      <p
        v-if="ventas.resumen.items_sin_costo"
        class="reportes__aviso"
      >
        {{ ventas.resumen.items_sin_costo }} ítems vendidos no tenían costo (entraron por un ajuste): la ganancia no los descuenta.
      </p>

      <div class="reportes__grid">
        <AppCard class="reportes__card">
          <h3 class="reportes__titulo">
            Por día
          </h3>
          <table class="reportes__tabla">
            <thead>
              <tr>
                <th>Fecha</th>
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
                v-for="dia in ventas.por_dia"
                :key="dia.fecha"
              >
                <td class="text-mono">
                  {{ fechaCorta(dia.fecha) }}
                </td>
                <td class="text-right text-mono">
                  {{ dia.ventas }}
                </td>
                <td class="text-right text-mono">
                  {{ formatearPrecio(dia.total) }}
                </td>
              </tr>
              <tr v-if="!ventas.por_dia.length">
                <td
                  colspan="3"
                  class="reportes__vacio"
                >
                  Sin ventas en el período.
                </td>
              </tr>
            </tbody>
          </table>
        </AppCard>

        <div class="reportes__columna">
          <AppCard class="reportes__card">
            <h3 class="reportes__titulo">
              Cobros por método
            </h3>
            <table class="reportes__tabla">
              <tbody>
                <tr
                  v-for="m in ventas.por_metodo"
                  :key="m.metodo"
                >
                  <td>{{ m.label }}</td>
                  <td class="text-right text-mono">
                    {{ m.cantidad }}
                  </td>
                  <td class="text-right text-mono">
                    {{ formatearPrecio(m.total) }}
                  </td>
                </tr>
                <tr v-if="!ventas.por_metodo.length">
                  <td class="reportes__vacio">
                    Sin cobros en el período.
                  </td>
                </tr>
              </tbody>
            </table>
          </AppCard>

          <AppCard class="reportes__card">
            <h3 class="reportes__titulo">
              Por canal
            </h3>
            <table class="reportes__tabla">
              <tbody>
                <tr
                  v-for="c in ventas.por_canal"
                  :key="c.canal"
                >
                  <td>{{ c.label }}</td>
                  <td class="text-right text-mono">
                    {{ c.ventas }}
                  </td>
                  <td class="text-right text-mono">
                    {{ formatearPrecio(c.total) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </AppCard>

          <AppCard
            v-if="!sedeId && ventas.por_sede.length > 1"
            class="reportes__card"
          >
            <h3 class="reportes__titulo">
              Por sede
            </h3>
            <table class="reportes__tabla">
              <tbody>
                <tr
                  v-for="s in ventas.por_sede"
                  :key="s.id"
                >
                  <td>{{ s.nombre }}</td>
                  <td class="text-right text-mono">
                    {{ s.ventas }}
                  </td>
                  <td class="text-right text-mono">
                    {{ formatearPrecio(s.total) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </AppCard>
        </div>
      </div>
    </template>

    <!-- ── Más vendidos ── -->
    <AppCard
      v-if="productos"
      class="reportes__card"
    >
      <h3 class="reportes__titulo">
        Más vendidos (por importe)
      </h3>
      <div class="reportes__scroll">
        <table class="reportes__tabla">
          <thead>
            <tr>
              <th>Producto</th>
              <th class="text-right">
                Cantidad
              </th>
              <th class="text-right">
                Importe
              </th>
              <th class="text-right">
                Ganancia
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="p in productos.data"
              :key="p.variante_id"
            >
              <td>
                <div class="reportes__fuerte">
                  {{ p.producto }}
                </div>
                <div class="reportes__meta">
                  {{ p.presentacion }} · <span class="text-mono">{{ p.sku }}</span>
                </div>
              </td>
              <td class="text-right text-mono">
                {{ formatearCantidad(p.cantidad) }} {{ p.unidad }}
              </td>
              <td class="text-right text-mono">
                {{ formatearPrecio(p.importe) }}
              </td>
              <td class="text-right text-mono">
                {{ p.ganancia === null ? '—' : formatearPrecio(p.ganancia) }}
              </td>
            </tr>
            <tr v-if="!productos.data.length">
              <td
                colspan="4"
                class="reportes__vacio"
              >
                Sin ventas en el período.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </AppCard>

    <!-- ── Inventario valorizado (hoy, no depende del rango) ── -->
    <template v-if="inventario">
      <h2 class="reportes__seccion">
        Inventario valorizado hoy
      </h2>
      <div class="reportes__tiles">
        <AppStatTile
          label="Valor del inventario (a costo)"
          :value="formatearPrecio(inventario.resumen.valor)"
        />
        <AppStatTile
          label="Presentaciones con stock"
          :value="String(inventario.resumen.presentaciones)"
        />
        <AppStatTile
          label="Por debajo del mínimo"
          :value="String(inventario.resumen.bajo_minimo)"
        />
        <AppStatTile
          label="Lotes vencidos"
          :value="String(inventario.resumen.lotes_vencidos)"
          :delta="inventario.resumen.valor_vencido ? formatearPrecio(inventario.resumen.valor_vencido) : ''"
          trend="down"
          :trend-is-good="false"
          delta-caption="inmovilizados"
        />
      </div>
      <p
        v-if="inventario.resumen.sin_costo"
        class="reportes__aviso"
      >
        {{ inventario.resumen.sin_costo }} presentaciones con stock no tienen costo (entraron por ajuste): no suman al valor.
      </p>

      <AppCard class="reportes__card">
        <h3 class="reportes__titulo">
          Donde está el capital
        </h3>
        <div class="reportes__scroll">
          <table class="reportes__tabla">
            <thead>
              <tr>
                <th>Producto</th>
                <th class="text-right">
                  Stock
                </th>
                <th class="text-right">
                  Costo prom.
                </th>
                <th class="text-right">
                  Valor
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="p in inventario.top"
                :key="p.variante_id"
              >
                <td>
                  <div class="reportes__fuerte">
                    {{ p.producto }}
                  </div>
                  <div class="reportes__meta">
                    {{ p.presentacion }} · <span class="text-mono">{{ p.sku }}</span>
                  </div>
                </td>
                <td class="text-right text-mono">
                  {{ formatearCantidad(p.cantidad) }} {{ p.unidad }}
                </td>
                <td class="text-right text-mono">
                  {{ p.costo_promedio === null ? '—' : formatearPrecio(p.costo_promedio) }}
                </td>
                <td class="text-right text-mono">
                  {{ formatearPrecio(p.valor) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AppCard from '@/components/AppCard.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppStatTile from '@/components/AppStatTile.vue'
import { fechaCorta, hoyLocal } from '@/modules/Cotizaciones/constantes'
import ReporteService from '@/services/ReporteService'
import SedeService from '@/services/SedeService'
import { useUserStore } from '@/stores/user-store'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'

const userStore = useUserStore()

// ── Rango ──
function inicioDeMes (desplazamiento = 0) {
  const d = new Date()
  d.setDate(1)
  d.setMonth(d.getMonth() + desplazamiento)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`
}

function finDeMesAnterior () {
  const d = new Date()
  d.setDate(0)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const RANGOS = [
  { label: 'Hoy', value: 'hoy' },
  { label: '7 días', value: '7d' },
  { label: 'Este mes', value: 'mes' },
  { label: 'Mes pasado', value: 'mes-pasado' }
]

const desde = ref(inicioDeMes())
const hasta = ref(hoyLocal())
const rango = ref('mes')

watch(rango, (valor) => {
  if (valor === 'hoy') [desde.value, hasta.value] = [hoyLocal(), hoyLocal()]
  if (valor === '7d') [desde.value, hasta.value] = [hoyLocal(-6), hoyLocal()]
  if (valor === 'mes') [desde.value, hasta.value] = [inicioDeMes(), hoyLocal()]
  if (valor === 'mes-pasado') [desde.value, hasta.value] = [inicioDeMes(-1), finDeMesAnterior()]
})

// ── Sede (vacío = todas) ──
const sedes = ref([])
const sedeId = ref(null)
const sedeOptions = computed(() => [
  { label: 'Todas las sedes', value: null },
  ...sedes.value.map((s) => ({ label: s.nombre, value: s.id }))
])

// ── Datos ──
const ventas = ref(null)
const productos = ref(null)
const inventario = ref(null)
const cargando = ref(false)

// Sólo vale la respuesta de la última consulta (filtros que cambian rápido).
let ultima = 0

async function cargar () {
  if (!desde.value || !hasta.value || desde.value > hasta.value) return

  const consulta = ++ultima
  cargando.value = true
  const params = { desde: desde.value, hasta: hasta.value, ...(sedeId.value ? { sede_id: sedeId.value } : {}) }
  try {
    const [v, p, i] = await Promise.all([
      userStore.hasPermission('reportes.ventas') ? ReporteService.ventas(params) : null,
      userStore.hasPermission('reportes.productos') ? ReporteService.productos(params) : null,
      userStore.hasPermission('reportes.inventario') ? ReporteService.inventario({ sede_id: sedeId.value ?? undefined }) : null
    ])
    if (consulta !== ultima) return
    ventas.value = v
    productos.value = p
    inventario.value = i
  } finally {
    if (consulta === ultima) cargando.value = false
  }
}

watch([desde, hasta, sedeId], cargar)

onMounted(async () => {
  cargar()
  if (userStore.hasPermission('sedes.index')) {
    sedes.value = await SedeService.activas()
  }
})
</script>

<style lang="scss" scoped>
.reportes {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.reportes__filtros {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}

.reportes__rangos {
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
}

.reportes__fecha {
  width: 170px;
}

.reportes__sede {
  min-width: 200px;
}

.reportes__tiles {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;

  @media (max-width: 1023px) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  @media (max-width: 599px) {
    grid-template-columns: 1fr;
  }
}

.reportes__grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  align-items: start;
  gap: 16px;

  @media (max-width: 1023px) {
    grid-template-columns: 1fr;
  }
}

.reportes__columna {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.reportes__card {
  padding: 16px;
}

.reportes__seccion {
  margin: 12px 0 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--app-ink);
}

.reportes__titulo {
  margin: 0 0 10px;
  font-size: 14px;
  font-weight: 600;
  color: var(--app-ink);
}

.reportes__scroll {
  overflow-x: auto;
}

.reportes__tabla {
  width: 100%;
  border-collapse: collapse;

  th {
    padding: 0 6px 6px;
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    color: var(--app-ink-2);
  }

  td {
    padding: 7px 6px;
    vertical-align: top;
    border-top: 1px solid var(--app-border-subtle);
    font-size: 13px;
    color: var(--app-ink);
  }

  .text-right {
    text-align: right;
  }
}

.reportes__fuerte {
  font-weight: 600;
}

.reportes__meta {
  font-size: 12px;
  color: var(--app-ink-2);
}

.reportes__vacio {
  text-align: center;
  color: var(--app-ink-2) !important;
}

.reportes__aviso {
  margin: -6px 0 0;
  font-size: 12px;
  color: var(--q-warning);
}
</style>
