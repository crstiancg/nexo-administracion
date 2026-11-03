<template>
  <div class="caja-resumen">
    <!-- ── Totales ── -->
    <div class="caja-resumen__tiles">
      <div class="caja-resumen__tile">
        <span class="caja-resumen__tileLabel">Apertura</span>
        <strong class="text-mono">{{ formatearPrecio(caja.resumen.monto_apertura) }}</strong>
      </div>
      <div class="caja-resumen__tile">
        <span class="caja-resumen__tileLabel">Cobrado (todos los métodos)</span>
        <strong class="text-mono">{{ formatearPrecio(caja.resumen.total_cobrado) }}</strong>
      </div>
      <div class="caja-resumen__tile">
        <span class="caja-resumen__tileLabel">Ingresos / egresos</span>
        <strong class="text-mono">
          +{{ formatearPrecio(caja.resumen.ingresos) }} / −{{ formatearPrecio(caja.resumen.egresos) }}
        </strong>
      </div>
      <div class="caja-resumen__tile caja-resumen__tile--destacado">
        <span class="caja-resumen__tileLabel">Efectivo esperado en caja</span>
        <strong class="text-mono">{{ formatearPrecio(caja.resumen.efectivo_esperado) }}</strong>
      </div>
    </div>

    <!-- Arqueo (sólo cerrada). -->
    <div
      v-if="caja.estado === 'cerrada' && caja.monto_contado === null"
      class="caja-resumen__arqueo caja-resumen__arqueo--sobrante"
    >
      Cerrada sin arqueo · esperado <span class="text-mono">{{ formatearPrecio(caja.monto_esperado) }}</span>
      <div
        v-if="caja.observacion_cierre"
        class="caja-resumen__detalle"
      >
        {{ caja.observacion_cierre }}
      </div>
    </div>
    <div
      v-else-if="caja.estado === 'cerrada'"
      :class="['caja-resumen__arqueo', claseDiferencia]"
    >
      Contado <strong class="text-mono">{{ formatearPrecio(caja.monto_contado) }}</strong>
      · esperado <span class="text-mono">{{ formatearPrecio(caja.monto_esperado) }}</span>
      · {{ textoDiferencia }}
      <div
        v-if="caja.observacion_cierre"
        class="caja-resumen__detalle"
      >
        {{ caja.observacion_cierre }}
      </div>
    </div>

    <!-- ── Ventas del turno (sólo quien puede ver costos) ── -->
    <section
      v-if="caja.ventas_turno"
      class="caja-resumen__ventas"
    >
      <h3 class="caja-resumen__titulo">
        Ventas del turno
        <span class="caja-resumen__detalle">
          · confirmadas mientras la caja estuvo abierta (no es lo cobrado: un adelanto no es venta)
        </span>
      </h3>
      <div class="caja-resumen__tiles">
        <div class="caja-resumen__tile">
          <span class="caja-resumen__tileLabel">Vendido ({{ caja.ventas_turno.ventas }} {{ caja.ventas_turno.ventas === 1 ? 'venta' : 'ventas' }})</span>
          <strong class="text-mono">{{ formatearPrecio(caja.ventas_turno.total) }}</strong>
        </div>
        <div class="caja-resumen__tile">
          <span class="caja-resumen__tileLabel">Costo de lo vendido</span>
          <strong class="text-mono">{{ formatearPrecio(caja.ventas_turno.costo) }}</strong>
        </div>
        <div :class="['caja-resumen__tile', 'caja-resumen__tile--destacado', { 'caja-resumen__tile--perdida': caja.ventas_turno.ganancia < 0 }]">
          <span class="caja-resumen__tileLabel">
            Ganancia<template v-if="caja.ventas_turno.margen !== null"> · {{ caja.ventas_turno.margen }}% de margen</template>
          </span>
          <strong class="text-mono">{{ formatearPrecio(caja.ventas_turno.ganancia) }}</strong>
        </div>
        <div class="caja-resumen__tile">
          <span class="caja-resumen__tileLabel">IGV incluido</span>
          <strong class="text-mono">{{ formatearPrecio(caja.ventas_turno.igv) }}</strong>
        </div>
      </div>
      <p
        v-if="caja.ventas_turno.items_sin_costo"
        class="caja-resumen__detalle"
      >
        {{ caja.ventas_turno.items_sin_costo }} ítems vendidos no tenían costo (entraron por un ajuste): la ganancia no los descuenta.
      </p>
    </section>

    <!-- ── Por método ── -->
    <section>
      <h3 class="caja-resumen__titulo">
        Cobros por método
      </h3>
      <ul class="caja-resumen__metodos">
        <li
          v-for="cobro in caja.resumen.cobros"
          :key="cobro.metodo"
          :class="{ 'caja-resumen__metodo--vacio': !cobro.cantidad }"
        >
          <span>{{ cobro.label }} <span class="caja-resumen__detalle">({{ cobro.cantidad }})</span></span>
          <span class="text-mono">{{ formatearPrecio(cobro.total) }}</span>
        </li>
      </ul>
    </section>

    <!-- ── Movimientos del día ── -->
    <section>
      <h3 class="caja-resumen__titulo">
        Pagos y movimientos
      </h3>
      <ul
        v-if="registros.length"
        class="caja-resumen__registros"
      >
        <li
          v-for="r in registros"
          :key="r.clave"
        >
          <div>
            <div class="caja-resumen__concepto">
              {{ r.concepto }}
            </div>
            <div class="caja-resumen__detalle">
              {{ formatearHora(r.fecha) }}<template v-if="r.detalle">
                · {{ r.detalle }}
              </template><template v-if="r.usuario">
                · {{ r.usuario }}
              </template>
            </div>
          </div>
          <span class="caja-resumen__derecha">
            <span :class="['text-mono', r.monto < 0 ? 'caja-resumen__menos' : 'caja-resumen__mas']">
              {{ r.monto < 0 ? '−' : '+' }}{{ formatearPrecio(Math.abs(r.monto)) }}
            </span>
            <!-- Ver la venta (para anularla, revisar su comprobante…). -->
            <q-btn
              v-if="r.pedidoId && userStore.hasPermission('pedidos.index')"
              flat
              dense
              round
              size="sm"
              icon="open_in_new"
              color="grey-7"
              :to="{ path: '/pedidos', query: { ver: r.pedidoId } }"
              :aria-label="`Ver ${r.pedidoCodigo}`"
            >
              <q-tooltip>Ver pedido</q-tooltip>
            </q-btn>
            <!-- Reimprimir el ticket de la venta de ese cobro. -->
            <q-btn
              v-if="r.pedidoId && puedeImprimir"
              flat
              dense
              round
              size="sm"
              icon="print"
              color="grey-7"
              :loading="imprimiendo === r.pedidoId"
              :aria-label="`Imprimir ticket de ${r.pedidoCodigo}`"
              @click="imprimir(r.pedidoId)"
            >
              <q-tooltip>Imprimir ticket</q-tooltip>
            </q-btn>
          </span>
        </li>
      </ul>
      <p
        v-else
        class="caja-resumen__detalle"
      >
        Todavía no hay pagos ni movimientos en esta caja.
      </p>
    </section>

    <ImpresionTicket ref="impresionRef" />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useQuasar } from 'quasar'
import ImpresionTicket from '@/modules/Ventas/ImpresionTicket.vue'
import PedidoService from '@/services/PedidoService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'

/**
 * Totales, arqueo y registros de una caja (la abierta o una del historial).
 * Sólo muestra: las acciones las pone la pantalla que lo usa.
 */
const props = defineProps({
  // CajaResource con `resumen`, `pagos` y `movimientos`.
  caja: {
    type: Object,
    required: true
  }
})

const formatoHora = new Intl.DateTimeFormat('es-PE', { hour: '2-digit', minute: '2-digit' })
function formatearHora (iso) {
  return iso ? formatoHora.format(new Date(iso)) : ''
}

// ── Reimprimir el ticket de un cobro ──
// Quien ve la caja y puede abrir pedidos (pedidos.show lo habilitan estos).
const $q = useQuasar()
const userStore = useUserStore()
const puedeImprimir = computed(() => ['pedidos.index', 'pedidos.update', 'ventas.store'].some((p) => userStore.hasPermission(p)))
const impresionRef = ref()
const imprimiendo = ref(null)

async function imprimir (pedidoId) {
  imprimiendo.value = pedidoId
  try {
    impresionRef.value.imprimir(await PedidoService.get(pedidoId))
  } catch {
    $q.notify({ type: 'negative', message: 'No se pudo cargar el ticket.', position: 'top-right' })
  } finally {
    imprimiendo.value = null
  }
}

// Pagos y movimientos en una sola lista, del más nuevo al más viejo.
const registros = computed(() => [
  ...(props.caja.pagos ?? []).map((p) => ({
    clave: `p${p.id}`,
    pedidoId: p.pedido?.id ?? null,
    pedidoCodigo: p.pedido?.codigo ?? '',
    fecha: p.fecha,
    monto: Number(p.monto),
    concepto: `${p.es_devolucion ? 'Devolución' : 'Cobro'} ${p.pedido?.codigo ?? ''} · ${p.metodo_label}`,
    detalle: [p.referencia && `Op. ${p.referencia}`, p.vuelto && Number(p.vuelto) > 0 && `vuelto ${formatearPrecio(p.vuelto)}`, p.motivo]
      .filter(Boolean).join(' · '),
    usuario: p.usuario?.name
  })),
  ...(props.caja.movimientos ?? []).map((m) => ({
    clave: `m${m.id}`,
    fecha: m.fecha,
    monto: m.tipo === 'egreso' ? -Number(m.monto) : Number(m.monto),
    concepto: `${m.tipo === 'egreso' ? 'Egreso' : 'Ingreso'} · ${m.concepto}`,
    detalle: '',
    usuario: m.usuario?.name
  }))
].sort((a, b) => b.fecha.localeCompare(a.fecha)))

const diferencia = computed(() => Number(props.caja.diferencia ?? 0))
const claseDiferencia = computed(() => {
  if (diferencia.value < 0) return 'caja-resumen__arqueo--faltante'
  if (diferencia.value > 0) return 'caja-resumen__arqueo--sobrante'
  return 'caja-resumen__arqueo--cuadra'
})
const textoDiferencia = computed(() => {
  if (diferencia.value < 0) return `faltante de ${formatearPrecio(-diferencia.value)}`
  if (diferencia.value > 0) return `sobrante de ${formatearPrecio(diferencia.value)}`
  return 'cuadra exacto'
})
</script>

<style lang="scss" scoped>
.caja-resumen {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.caja-resumen__tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 12px;
}

.caja-resumen__tile {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
  background: var(--app-surface);

  strong {
    font-size: 18px;
    color: var(--app-ink);
  }

  &--destacado {
    border-color: $primary;
    background: rgba($primary, 0.06);

    strong {
      font-size: 22px;
    }
  }
}

.caja-resumen__tileLabel {
  font-size: 12px;
  color: var(--app-ink-2);
}

.caja-resumen__arqueo {
  padding: 12px 14px;
  border-radius: 10px;
  font-size: 13.5px;
  color: var(--app-ink);

  &--cuadra {
    background: rgba($positive, 0.1);
  }

  &--sobrante {
    background: rgba($warning, 0.14);
  }

  &--faltante {
    background: rgba($negative, 0.1);
  }
}

.caja-resumen__ventas {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.caja-resumen__tile--perdida strong {
  color: var(--q-negative);
}

.caja-resumen__titulo {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.caja-resumen__metodos,
.caja-resumen__derecha {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 4px;
}

.caja-resumen__registros {
  margin: 0;
  padding: 0;
  list-style: none;

  li {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 0;
    border-top: 1px solid var(--app-border-subtle);
    font-size: 13px;
    color: var(--app-ink);
  }
}

.caja-resumen__metodo--vacio {
  color: var(--app-ink-2) !important;
}

.caja-resumen__concepto {
  font-weight: 600;
}

.caja-resumen__detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.caja-resumen__mas {
  font-weight: 600;
  color: var(--q-positive);
}

.caja-resumen__menos {
  font-weight: 600;
  color: var(--q-negative);
}
</style>
