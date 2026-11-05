<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Kardex valorizado"
      subtitle="Entradas, salidas y saldo de una presentación, en cantidad y en soles (costo promedio ponderado)"
    >
      <template #actions>
        <AppButton
          v-if="kardex && userStore.hasPermission('reportes.inventario')"
          variant="secondary"
          label="Excel"
          icon="mdi-microsoft-excel"
          :loading="bajando"
          @click="bajarExcel"
        />
      </template>
    </AppPageHeader>

    <!-- ── Qué y cuándo ── -->
    <div class="kdx-filtros">
      <BuscadorVariante
        class="kdx-buscador"
        :label="kardex ? `${kardex.variante.producto} ${kardex.variante.presentacion} · cambiar: SKU o nombre` : 'Presentación: SKU o nombre del producto'"
        @elegir="(v) => { varianteId = v.id }"
      />
      <q-select
        v-if="sedes.length > 1"
        v-model="sedeId"
        :options="sedeOptions"
        emit-value
        map-options
        dense
        outlined
        label="Sede"
        class="kdx-control"
      />
      <q-input
        v-model="desde"
        type="date"
        dense
        outlined
        clearable
        label="Desde"
        stack-label
        class="kdx-control"
      />
      <q-input
        v-model="hasta"
        type="date"
        dense
        outlined
        clearable
        label="Hasta"
        stack-label
        class="kdx-control"
      />
    </div>

    <p
      v-if="!varianteId"
      class="kdx-vacio"
    >
      <q-icon
        name="search"
        size="22px"
      />
      Elige una presentación para ver su kardex.
    </p>

    <div
      v-else-if="cargando && !kardex"
      class="kdx-vacio"
    >
      <q-spinner size="24px" />
    </div>

    <template v-else-if="kardex">
      <!-- ── En resumen ── -->
      <dl class="kdx-resumen">
        <div>
          <dt>Saldo inicial</dt>
          <dd class="text-mono">
            {{ cantidad(kardex.saldo_inicial.cantidad) }}
          </dd>
          <dd class="kdx-muted text-mono">
            {{ precio(kardex.saldo_inicial.valor) }}
          </dd>
        </div>
        <div>
          <dt>Entradas</dt>
          <dd class="text-mono kdx-entra">
            +{{ cantidad(kardex.totales.entradas) }}
          </dd>
          <dd class="kdx-muted text-mono">
            {{ precio(kardex.totales.entradas_valor) }}
          </dd>
        </div>
        <div>
          <dt>Salidas</dt>
          <dd class="text-mono kdx-sale">
            −{{ cantidad(kardex.totales.salidas) }}
          </dd>
          <dd class="kdx-muted text-mono">
            {{ precio(kardex.totales.salidas_valor) }}
          </dd>
        </div>
        <div>
          <dt>Saldo final</dt>
          <dd class="text-mono">
            {{ cantidad(kardex.saldo_final.cantidad) }}
          </dd>
          <dd class="kdx-muted text-mono">
            {{ precio(kardex.saldo_final.valor) }}
            <template v-if="kardex.saldo_final.costo !== null">
              · a {{ precio(kardex.saldo_final.costo) }} c/u
            </template>
          </dd>
        </div>
      </dl>

      <div class="kdx-tablaWrap">
        <table class="kdx-tabla">
          <thead>
            <tr>
              <th rowspan="2">
                Fecha
              </th>
              <th rowspan="2">
                Detalle
              </th>
              <th
                colspan="3"
                class="kdx-grupo"
              >
                Entrada / salida
              </th>
              <th
                colspan="3"
                class="kdx-grupo"
              >
                Saldo
              </th>
            </tr>
            <tr>
              <th class="text-right">
                Cantidad
              </th>
              <th class="text-right">
                Costo unit.
              </th>
              <th class="text-right">
                Importe
              </th>
              <th class="text-right">
                Cantidad
              </th>
              <th class="text-right">
                Costo prom.
              </th>
              <th class="text-right">
                Valorizado
              </th>
            </tr>
          </thead>
          <tbody>
            <tr class="kdx-inicial">
              <td />
              <td>Saldo inicial</td>
              <td colspan="3" />
              <td class="text-right text-mono">
                {{ cantidad(kardex.saldo_inicial.cantidad) }}
              </td>
              <td class="text-right text-mono">
                {{ precio(kardex.saldo_inicial.costo) }}
              </td>
              <td class="text-right text-mono">
                {{ precio(kardex.saldo_inicial.valor) }}
              </td>
            </tr>
            <tr
              v-for="f in kardex.filas"
              :key="f.id"
            >
              <td class="text-mono kdx-fecha">
                {{ formatearFechaHora(f.fecha) }}
              </td>
              <td>
                {{ f.detalle }}
                <div class="kdx-muted">
                  <template v-if="!kardex.sede && f.sede">
                    {{ f.sede }} ·
                  </template>{{ f.usuario ?? 'Sistema' }}
                </div>
              </td>
              <td :class="['text-right text-mono', f.entrada ? 'kdx-entra' : 'kdx-sale']">
                {{ f.entrada ? `+${cantidad(f.entrada)}` : `−${cantidad(f.salida)}` }}
              </td>
              <td class="text-right text-mono">
                {{ precio(f.costo_unitario) }}
              </td>
              <td class="text-right text-mono">
                {{ precio(f.importe) }}
              </td>
              <td class="text-right text-mono">
                {{ cantidad(f.saldo) }}
              </td>
              <td class="text-right text-mono">
                {{ precio(f.costo_promedio) }}
              </td>
              <td class="text-right text-mono">
                {{ precio(f.saldo_valor) }}
              </td>
            </tr>
            <tr v-if="!kardex.filas.length">
              <td
                colspan="8"
                class="kdx-muted"
              >
                Sin movimientos en este período.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="kdx-muted">
        Las entradas van a su costo de compra y las salidas al costo promedio del momento. Una entrada sin costo
        (un ajuste) no cambia el promedio.
      </p>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import BuscadorVariante from '@/modules/Inventario/BuscadorVariante.vue'
import ReporteService from '@/services/ReporteService'
import SedeService from '@/services/SedeService'
import { useUserStore } from '@/stores/user-store'
import { formatearCantidad } from '@/utils/cantidad'
import { descargarArchivo } from '@/utils/descargar'
import { formatearFechaHora } from '@/utils/fechas'
import { formatearPrecio } from '@/utils/moneda'

const route = useRoute()
const userStore = useUserStore()

// ?variante=ID (desde la ficha del producto): abre con esa presentación.
const varianteId = ref(Number(route.query.variante) || null)
const sedeId = ref(null)
const desde = ref(null)
const hasta = ref(null)
const kardex = ref(null)
const cargando = ref(false)
const bajando = ref(false)

const sedes = ref([])
const sedeOptions = computed(() => [{ label: 'Toda la empresa', value: null }, ...sedes.value.map((s) => ({ label: s.nombre, value: s.id }))])

const cantidad = (n) => formatearCantidad(n ?? 0)
const precio = (n) => (n === null || n === undefined ? '—' : formatearPrecio(n))

function params () {
  return {
    variante_id: varianteId.value,
    sede_id: sedeId.value ?? undefined,
    desde: desde.value || undefined,
    hasta: hasta.value || undefined
  }
}

async function cargar () {
  if (!varianteId.value) return
  cargando.value = true
  try {
    kardex.value = await ReporteService.kardex(params())
  } finally {
    cargando.value = false
  }
}

async function bajarExcel () {
  bajando.value = true
  try {
    await descargarArchivo('api/reportes/kardex/excel', `kardex-${kardex.value.variante.sku}.xlsx`, params())
  } finally {
    bajando.value = false
  }
}

watch([varianteId, sedeId, desde, hasta], cargar)

onMounted(async () => {
  if (userStore.hasPermission('sedes.index')) {
    sedes.value = await SedeService.activas().catch(() => [])
  }
  cargar()
})
</script>

<style lang="scss" scoped>
.kdx-filtros {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.kdx-buscador {
  flex: 1 1 320px;
  min-width: 0;
}

.kdx-control {
  width: 170px;
}

.kdx-vacio {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 48px 16px;
  color: var(--app-ink-2);
}

.kdx-resumen {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 10px;
  margin: 0 0 16px;

  > div {
    padding: 12px 14px;
    border: 1px solid var(--app-border-subtle);
    border-radius: 12px;
    background: var(--app-surface);
  }

  dt {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    color: var(--app-ink-2);
  }

  dd {
    margin: 2px 0 0;
    font-size: 18px;
    font-weight: 700;
    color: var(--app-ink);
  }

  dd.kdx-muted {
    font-size: 12.5px;
    font-weight: 500;
  }
}

.kdx-tablaWrap {
  overflow-x: auto;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
  background: var(--app-surface);
}

.kdx-tabla {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;

  th {
    padding: 8px 10px;
    border-bottom: 1px solid var(--app-border-subtle);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.4px;
    text-align: left;
    text-transform: uppercase;
    white-space: nowrap;
    color: var(--app-ink-2);
  }

  td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--app-border-subtle);
    color: var(--app-ink);
    vertical-align: top;
  }
}

.kdx-grupo {
  text-align: center !important;
  border-left: 1px solid var(--app-border-subtle);
}

.kdx-inicial td {
  font-style: italic;
  color: var(--app-ink-2);
}

.kdx-fecha {
  white-space: nowrap;
}

.kdx-muted {
  font-size: 12px;
  color: var(--app-ink-2);
}

.kdx-entra {
  color: var(--q-positive);
}

.kdx-sale {
  color: var(--q-negative);
}
</style>
