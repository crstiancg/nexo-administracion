<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Ganancias"
      :subtitle="`Lo que realmente quedó · ${rangoTexto}`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('movimientos-financieros.store')"
          variant="secondary"
          label="Registrar gasto"
          icon="north_east"
          to="/finanzas"
        />
      </template>
    </AppPageHeader>

    <!-- Período -->
    <div class="gan-periodo">
      <div
        class="gan-periodos"
        role="radiogroup"
        aria-label="Período"
      >
        <button
          v-for="p in PERIODOS"
          :key="p.value"
          type="button"
          role="radio"
          :aria-checked="String(periodo === p.value)"
          :class="['gan-periodo__btn', { 'gan-periodo__btn--activo': periodo === p.value }]"
          @click="periodo = p.value"
        >
          {{ p.label }}
        </button>
      </div>
      <template v-if="periodo === 'rango'">
        <q-input
          v-model="desde"
          type="date"
          dense
          outlined
          aria-label="Desde"
          class="gan-fecha"
        />
        <q-input
          v-model="hasta"
          type="date"
          dense
          outlined
          aria-label="Hasta"
          class="gan-fecha"
        />
      </template>
    </div>

    <div
      v-if="cargando && !datos"
      class="gan-cargando"
    >
      <q-spinner size="28px" />
    </div>

    <template v-else-if="datos">
      <!-- La cuenta, de un vistazo -->
      <div class="gan-tiles">
        <AppStatTile
          label="Ingresos"
          :value="formatearPrecio(datos.ingresos)"
        />
        <AppStatTile
          label="Costo de lo vendido"
          :value="formatearPrecio(datos.costo)"
        />
        <AppStatTile
          label="Gastos"
          :value="formatearPrecio(datos.gastos)"
        />
        <AppStatTile
          featured
          label="Utilidad"
          :value="formatearPrecio(datos.utilidad)"
          :delta="datos.margen !== null ? `${datos.margen}%` : ''"
          :trend="datos.utilidad >= 0 ? 'up' : 'down'"
          :trend-is-good="datos.utilidad >= 0"
          delta-caption="de margen"
        />
      </div>

      <p
        v-if="datos.items_sin_costo"
        class="gan-aviso"
      >
        <q-icon name="info" />
        {{ datos.items_sin_costo }} {{ datos.items_sin_costo === 1 ? 'producto vendido no tiene' : 'productos vendidos no tienen' }}
        costo registrado (entraron por ajuste): la utilidad real puede ser algo menor.
      </p>

      <div class="gan-fila">
        <!-- De dónde vino -->
        <AppCard class="gan-card">
          <h2 class="gan-titulo">
            De dónde vino la plata
          </h2>
          <ul class="gan-barras">
            <li
              v-for="o in origenes"
              :key="o.label"
            >
              <span class="gan-barras__nombre">
                <q-icon
                  :name="o.icono"
                  size="16px"
                />
                {{ o.label }}
                <span
                  v-if="o.cantidad !== undefined"
                  class="gan-muted"
                >· {{ o.cantidad }}</span>
              </span>
              <span class="text-mono gan-barras__valor">{{ formatearPrecio(o.total) }}</span>
              <span class="gan-barras__barra">
                <span :style="{ width: `${porcentaje(o.total, datos.ingresos)}%` }" />
              </span>
            </li>
          </ul>
          <p class="gan-muted">
            Incluye {{ formatearPrecio(datos.igv) }} de IGV cobrado (se declara a SUNAT).
          </p>
        </AppCard>

        <!-- En qué se fue -->
        <AppCard class="gan-card">
          <div class="gan-cabecera">
            <h2 class="gan-titulo">
              En qué se fue
            </h2>
            <router-link
              v-if="userStore.hasPermission('movimientos-financieros.index')"
              to="/finanzas"
              class="gan-link"
            >
              Ver gastos
            </router-link>
          </div>
          <p
            v-if="!datos.gastos_por_concepto.length"
            class="gan-muted"
          >
            Sin gastos registrados en este período.
          </p>
          <ul
            v-else
            class="gan-barras gan-barras--gasto"
          >
            <li
              v-for="g in datos.gastos_por_concepto"
              :key="g.concepto"
            >
              <span class="gan-barras__nombre">{{ g.concepto }}</span>
              <span class="text-mono gan-barras__valor">{{ formatearPrecio(g.total) }}</span>
              <span class="gan-barras__barra">
                <span :style="{ width: `${porcentaje(g.total, datos.gastos_por_concepto[0].total)}%` }" />
              </span>
            </li>
          </ul>
        </AppCard>
      </div>

      <!-- Por técnico -->
      <AppCard
        v-if="datos.por_tecnico.length"
        class="gan-card"
      >
        <h2 class="gan-titulo">
          Ganancia por técnico
        </h2>
        <p class="gan-muted">
          Órdenes entregadas en el período: lo cobrado menos el costo de los repuestos.
        </p>
        <div class="gan-tablaWrap">
          <table class="gan-tabla">
            <thead>
              <tr>
                <th>Técnico</th>
                <th class="text-right">
                  Órdenes
                </th>
                <th class="text-right">
                  Mano de obra
                </th>
                <th class="text-right">
                  Cobrado
                </th>
                <th class="text-right">
                  Ganancia
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="t in datos.por_tecnico"
                :key="t.tecnico"
              >
                <td>{{ t.tecnico }}</td>
                <td class="text-right text-mono">
                  {{ t.ordenes }}
                </td>
                <td class="text-right text-mono">
                  {{ formatearPrecio(t.mano_obra) }}
                </td>
                <td class="text-right text-mono">
                  {{ formatearPrecio(t.cobrado) }}
                </td>
                <td class="text-right text-mono gan-fuerte">
                  {{ formatearPrecio(t.ganancia) }}
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
import { computed, ref, watch } from 'vue'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppStatTile from '@/components/AppStatTile.vue'
import { fechaCorta, hoyLocal } from '@/modules/Cotizaciones/constantes'
import FinanzaService from '@/services/FinanzaService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'

const userStore = useUserStore()

const PERIODOS = [
  { value: 'mes', label: 'Este mes' },
  { value: 'anterior', label: 'Mes anterior' },
  { value: 'anio', label: 'Este año' },
  { value: 'rango', label: 'Rango…' }
]

const periodo = ref('mes')
const hoy = hoyLocal()
const desde = ref(`${hoy.slice(0, 7)}-01`)
const hasta = ref(hoy)

function iso (d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

// El rango que se pide según el período elegido.
const rango = computed(() => {
  const ahora = new Date()
  if (periodo.value === 'anterior') {
    return { desde: iso(new Date(ahora.getFullYear(), ahora.getMonth() - 1, 1)), hasta: iso(new Date(ahora.getFullYear(), ahora.getMonth(), 0)) }
  }
  if (periodo.value === 'anio') return { desde: `${ahora.getFullYear()}-01-01`, hasta: hoy }
  if (periodo.value === 'rango') return { desde: desde.value, hasta: hasta.value }
  return { desde: `${hoy.slice(0, 7)}-01`, hasta: hoy }
})

const rangoTexto = computed(() => `${fechaCorta(rango.value.desde)} al ${fechaCorta(rango.value.hasta)}`)

const datos = ref(null)
const cargando = ref(false)

async function cargar () {
  if (!rango.value.desde || !rango.value.hasta || rango.value.desde > rango.value.hasta) return
  cargando.value = true
  try {
    datos.value = await FinanzaService.ganancias(rango.value)
  } finally {
    cargando.value = false
  }
}

watch(rango, cargar, { immediate: true })

const origenes = computed(() => [
  { label: 'Ventas', icono: 'storefront', total: datos.value.ventas.total, cantidad: datos.value.ventas.cantidad },
  { label: 'Reparaciones', icono: 'build', total: datos.value.reparaciones.total, cantidad: datos.value.reparaciones.cantidad },
  { label: 'Otros ingresos', icono: 'south_west', total: datos.value.otros_ingresos }
])

function porcentaje (valor, base) {
  return base > 0 ? Math.max(0, Math.min(100, (valor / base) * 100)) : 0
}
</script>

<style lang="scss" scoped>
.gan-periodo {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.gan-periodos {
  display: inline-flex;
  flex-wrap: wrap;
  padding: 3px;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  background: var(--app-surface);
}

.gan-periodo__btn {
  padding: 6px 12px;
  border: 0;
  border-radius: 7px;
  background: none;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &--activo {
    background: var(--app-brand-soft);
    color: var(--app-brand-soft-ink);
  }
}

.gan-fecha {
  width: 160px;

  :deep(.q-field__control) {
    border-radius: 10px;
  }
}

.gan-cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}

.gan-tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 14px;
}

.gan-aviso {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--app-tint-warning);
  font-size: 13px;
  color: var(--app-ink-warning);
}

.gan-fila {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 14px;
}

.gan-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
  padding: 18px 20px;
}

.gan-cabecera {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.gan-titulo {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--app-ink);
}

.gan-link {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-brand-soft-ink);
  text-decoration: none;
}

.gan-muted {
  margin: 0;
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.gan-barras {
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

.gan-barras__nombre {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--app-ink);
}

.gan-barras__valor {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.gan-barras__barra {
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

.gan-barras--gasto .gan-barras__barra {
  background: var(--app-tint-negative);

  span {
    background: var(--app-ink-negative);
  }
}

.gan-tablaWrap {
  overflow-x: auto;
}

.gan-tabla {
  width: 100%;
  min-width: 480px;
  border-collapse: collapse;
  font-size: 13.5px;

  th {
    padding: 6px 8px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-align: left;
    text-transform: uppercase;
    color: var(--app-ink-2);
  }

  td {
    padding: 8px;
    border-top: 1px solid var(--app-border-subtle);
    color: var(--app-ink);
  }

  .text-right {
    text-align: right;
  }
}

.gan-fuerte {
  font-weight: 700;
}
</style>
