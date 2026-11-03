<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Comisiones"
      :subtitle="`Del ${fechaCorta(rango.desde)} al ${fechaCorta(rango.hasta)} · técnicos por la mano de obra que entregan, vendedores por lo que venden`"
    >
      <template #actions>
        <q-input
          v-model="mes"
          type="month"
          dense
          outlined
          aria-label="Mes"
          class="com-mes"
        />
        <AppButton
          v-if="userStore.hasPermission('comisiones.porcentajes')"
          variant="secondary"
          label="Guardar porcentajes"
          icon="save"
          :loading="guardando"
          :disable="!cambios"
          @click="guardarPorcentajes"
        />
      </template>
    </AppPageHeader>

    <div
      v-if="cargando && !filas.length"
      class="com-cargando"
    >
      <q-spinner size="28px" />
    </div>

    <AppCard
      v-else
      class="com-card"
    >
      <div class="com-tablaWrap">
        <table class="com-tabla">
          <thead>
            <tr>
              <th>Empleado</th>
              <th class="text-right">
                % reparación
              </th>
              <th class="text-right">
                % venta
              </th>
              <th class="text-right">
                Reparaciones
              </th>
              <th class="text-right">
                Ventas
              </th>
              <th class="text-right">
                Comisión
              </th>
              <th class="text-right">
                Estado
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="f in filas"
              :key="f.id"
            >
              <td class="com-nombre">
                {{ f.nombre }}
              </td>
              <td class="text-right">
                <q-input
                  v-model.number="f.pct_reparacion"
                  type="number"
                  min="0"
                  max="100"
                  step="0.5"
                  suffix="%"
                  dense
                  outlined
                  :readonly="!userStore.hasPermission('comisiones.porcentajes')"
                  :aria-label="`% de reparación de ${f.nombre}`"
                  class="com-pct"
                  @update:model-value="cambios = true"
                />
              </td>
              <td class="text-right">
                <q-input
                  v-model.number="f.pct_venta"
                  type="number"
                  min="0"
                  max="100"
                  step="0.5"
                  suffix="%"
                  dense
                  outlined
                  :readonly="!userStore.hasPermission('comisiones.porcentajes')"
                  :aria-label="`% de venta de ${f.nombre}`"
                  class="com-pct"
                  @update:model-value="cambios = true"
                />
              </td>
              <td class="text-right">
                <span class="text-mono">{{ formatearPrecio(f.base_reparacion) }}</span>
                <div class="com-meta">
                  {{ f.reparaciones }} {{ f.reparaciones === 1 ? 'orden' : 'órdenes' }}
                </div>
              </td>
              <td class="text-right">
                <span class="text-mono">{{ formatearPrecio(f.base_venta) }}</span>
                <div class="com-meta">
                  {{ f.ventas }} {{ f.ventas === 1 ? 'venta' : 'ventas' }}
                </div>
              </td>
              <td class="text-right com-total text-mono">
                {{ formatearPrecio(f.total) }}
              </td>
              <td class="text-right">
                <AppChip
                  v-if="f.pagado !== null"
                  status="positive"
                  :label="`Pagado ${formatearPrecio(f.pagado)}`"
                />
                <q-btn
                  v-else-if="f.total > 0 && userStore.hasPermission('comisiones.pagar')"
                  unelevated
                  dense
                  no-caps
                  color="primary"
                  label="Pagar"
                  class="com-pagar"
                  :disable="cambios"
                  @click="abrirPago(f)"
                >
                  <q-tooltip v-if="cambios">
                    Guarda los porcentajes primero
                  </q-tooltip>
                </q-btn>
                <span
                  v-else
                  class="com-meta"
                >—</span>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="filas.length">
            <tr>
              <td colspan="5">
                Total del período
              </td>
              <td class="text-right com-total text-mono">
                {{ formatearPrecio(totalPeriodo) }}
              </td>
              <td />
            </tr>
          </tfoot>
        </table>
      </div>
      <p class="com-ayuda">
        Al pagar se registra como gasto "Comisiones" (aparece en Ingresos y gastos y descuenta la Ganancia).
        Cada período se paga una sola vez.
      </p>
    </AppCard>

    <AppDialog
      v-model="pagoDialog"
      :title="`Pagar comisión de ${aPagar?.nombre ?? ''}`"
      persistent
    >
      <div class="com-pago">
        <p class="com-pago__monto">
          <span>Monto</span>
          <strong class="text-mono">{{ formatearPrecio(aPagar?.total) }}</strong>
        </p>
        <div
          class="com-metodos"
          role="radiogroup"
          aria-label="Método"
        >
          <button
            v-for="m in METODOS"
            :key="m.value"
            type="button"
            role="radio"
            :aria-checked="String(pago.metodo === m.value)"
            :class="['com-metodo', { 'com-metodo--activo': pago.metodo === m.value }]"
            @click="pago.metodo = m.value"
          >
            {{ m.label }}
          </button>
        </div>
        <q-toggle
          v-if="pago.metodo === 'efectivo'"
          v-model="pago.de_caja"
          label="Sale del efectivo de la caja abierta"
          color="primary"
        />
        <p
          v-if="errorPago"
          class="com-error"
          role="alert"
        >
          {{ errorPago }}
        </p>
      </div>
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="pagoDialog = false"
        />
        <AppButton
          variant="primary"
          label="Registrar pago"
          :loading="pagando"
          @click="pagar"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import { METODOS } from '@/modules/Caja/constantes'
import { fechaCorta, hoyLocal } from '@/modules/Cotizaciones/constantes'
import ComisionService from '@/services/ComisionService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'

const $q = useQuasar()
const userStore = useUserStore()

const mes = ref(hoyLocal().slice(0, 7))
const rango = computed(() => {
  const [a, m] = mes.value.split('-').map(Number)
  const ultimo = new Date(Date.UTC(a, m, 0)).getUTCDate()
  const hasta = `${mes.value}-${String(ultimo).padStart(2, '0')}`
  return { desde: `${mes.value}-01`, hasta: hasta > hoyLocal() ? hoyLocal() : hasta }
})

const filas = ref([])
const cargando = ref(false)
const cambios = ref(false)

async function cargar () {
  if (!mes.value) return
  cargando.value = true
  try {
    filas.value = (await ComisionService.get(rango.value)).data
    cambios.value = false
  } finally {
    cargando.value = false
  }
}
watch(rango, cargar, { immediate: true })

const totalPeriodo = computed(() => filas.value.reduce((s, f) => s + f.total, 0))

const guardando = ref(false)
async function guardarPorcentajes () {
  guardando.value = true
  try {
    const empleados = filas.value.map((f) => ({ id: f.id, reparacion: Number(f.pct_reparacion) || 0, venta: Number(f.pct_venta) || 0 }))
    filas.value = (await ComisionService.porcentajes(empleados, rango.value)).data
    cambios.value = false
    $q.notify({ type: 'positive', message: 'Porcentajes guardados.', position: 'top-right', timeout: 1500 })
  } catch (e) {
    $q.notify({ type: 'negative', message: Object.values(e.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudieron guardar.', position: 'top-right' })
  } finally {
    guardando.value = false
  }
}

// ── Pagar ──
const pagoDialog = ref(false)
const aPagar = ref(null)
const pago = reactive({ metodo: 'efectivo', de_caja: true })
const pagando = ref(false)
const errorPago = ref('')

function abrirPago (fila) {
  aPagar.value = fila
  errorPago.value = ''
  pagoDialog.value = true
}

async function pagar () {
  pagando.value = true
  errorPago.value = ''
  try {
    filas.value = (await ComisionService.pagar(aPagar.value.id, { metodo: pago.metodo, de_caja: pago.metodo === 'efectivo' && pago.de_caja }, rango.value)).data
    pagoDialog.value = false
    $q.notify({ type: 'positive', message: `Comisión de ${aPagar.value.nombre} pagada.`, position: 'top-right', timeout: 1800 })
  } catch (e) {
    errorPago.value = Object.values(e.response?.data?.errors ?? {}).flat()[0] ?? e.response?.data?.message ?? 'No se pudo pagar.'
  } finally {
    pagando.value = false
  }
}
</script>

<style lang="scss" scoped>
.com-mes {
  width: 170px;

  :deep(.q-field__control) {
    height: 40px;
    border-radius: 10px;
  }
}

.com-cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}

.com-card {
  padding: 8px 4px 16px;
}

.com-tablaWrap {
  overflow-x: auto;
}

.com-tabla {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
  font-size: 13.5px;

  th {
    padding: 10px 12px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-align: left;
    text-transform: uppercase;
    color: var(--app-ink-2);
  }

  td {
    padding: 8px 12px;
    border-top: 1px solid var(--app-border-subtle);
    color: var(--app-ink);
    vertical-align: middle;
  }

  tfoot td {
    font-weight: 700;
  }

  .text-right {
    text-align: right;
  }
}

.com-nombre {
  font-weight: 600;
}

.com-pct {
  width: 96px;
  margin-left: auto;

  :deep(.q-field__control) {
    border-radius: 8px;
  }
}

.com-meta {
  font-size: 12px;
  color: var(--app-ink-2);
}

.com-total {
  font-weight: 700;
}

.com-pagar {
  padding: 0 14px;
  border-radius: 8px;
}

.com-ayuda {
  margin: 12px 12px 0;
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.com-pago {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.com-pago__monto {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 0;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--app-brand-soft);
  font-size: 14px;

  strong {
    font-size: 20px;
  }
}

.com-metodos {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 8px;
}

.com-metodo {
  height: 38px;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  background: var(--app-surface);
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &--activo {
    border-color: $primary;
    background: var(--app-brand-soft);
    color: var(--app-brand-soft-ink);
  }
}

.com-error {
  margin: 0;
  font-size: 12.5px;
  color: var(--q-negative);
}
</style>
