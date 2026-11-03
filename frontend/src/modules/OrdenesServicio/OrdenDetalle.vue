<template>
  <div
    v-if="orden"
    class="os-detalle"
  >
    <header class="os-detalle__cabecera">
      <div>
        <div class="os-detalle__codigo text-mono">
          {{ orden.codigo }}
        </div>
        <div class="os-detalle__meta">
          Recibido {{ formatearFechaHora(orden.recibido_at) }} · {{ orden.sede?.nombre }}
          <template v-if="orden.usuario">
            · por {{ orden.usuario.name }}
          </template>
        </div>
      </div>
      <div class="os-detalle__chips">
        <AppChip
          v-if="orden.es_garantia"
          status="info"
          label="Por garantía"
        />
        <AppChip
          :status="ESTADOS[orden.estado].status"
          :label="ESTADOS[orden.estado].label"
        />
      </div>
    </header>

    <div class="os-detalle__grid">
      <section class="os-detalle__card">
        <h4>Cliente</h4>
        <div class="os-detalle__fuerte">
          {{ orden.cliente?.nombre }}
        </div>
        <div v-if="orden.cliente?.numero_documento">
          {{ orden.cliente.tipo_documento }} {{ orden.cliente.numero_documento }}
        </div>
        <div v-if="orden.cliente?.telefono">
          <q-icon name="call" /> {{ orden.cliente.telefono }}
        </div>
        <div class="os-detalle__sub">
          Técnico: {{ orden.tecnico?.name ?? 'Sin asignar' }}
        </div>
      </section>

      <section class="os-detalle__card">
        <h4>Equipo</h4>
        <div class="os-detalle__fuerte">
          {{ orden.equipo }}
        </div>
        <div v-if="orden.serie">
          Serie / IMEI: <span class="text-mono">{{ orden.serie }}</span>
        </div>
        <div v-if="orden.accesorios">
          Accesorios: {{ orden.accesorios }}
        </div>
        <div>
          Bloqueo: {{ orden.seguridad_label }}
          <template v-if="orden.seguridad_valor">
            ·
            <button
              type="button"
              class="os-detalle__revelar text-mono"
              @click="verClave = !verClave"
            >
              {{ verClave ? orden.seguridad_valor : '••••  ver' }}
            </button>
          </template>
        </div>
      </section>
    </div>

    <div
      v-if="puntos.length"
      class="os-detalle__checklist"
    >
      <span
        v-for="[punto, ok] in puntos"
        :key="punto"
        :class="['os-detalle__punto', ok ? 'os-detalle__punto--ok' : 'os-detalle__punto--no']"
      >
        <q-icon :name="ok ? 'check' : 'close'" /> {{ punto }}
      </span>
    </div>

    <section class="os-detalle__card">
      <h4>Falla</h4>
      <p>{{ orden.falla }}</p>
      <template v-if="orden.diagnostico">
        <h4>Diagnóstico / trabajo</h4>
        <p>{{ orden.diagnostico }}</p>
      </template>
      <p
        v-if="orden.observacion"
        class="os-detalle__nota"
      >
        <strong>Nota interna:</strong> {{ orden.observacion }}
      </p>
    </section>

    <!-- ── Cobro ── -->
    <section class="os-detalle__cobro">
      <div class="os-detalle__lineas">
        <div
          v-for="item in orden.repuestos"
          :key="item.id"
          class="os-detalle__linea"
        >
          <span>{{ formatearCantidad(item.cantidad) }} × {{ item.variante?.producto?.nombre }} {{ item.variante?.presentacion }}</span>
          <span class="text-mono">{{ formatearPrecio(item.subtotal) }}</span>
        </div>
        <div
          v-if="Number(orden.mano_obra)"
          class="os-detalle__linea"
        >
          <span>Mano de obra</span>
          <span class="text-mono">{{ formatearPrecio(orden.mano_obra) }}</span>
        </div>
        <p
          v-if="!orden.repuestos?.length && !Number(orden.mano_obra)"
          class="os-detalle__sub"
        >
          Todavía sin presupuesto.
        </p>
      </div>
      <dl class="os-detalle__totales">
        <dt>Total</dt>
        <dd class="text-mono">
          {{ formatearPrecio(orden.total) }}
        </dd>
        <dt>Abonado</dt>
        <dd class="text-mono">
          {{ formatearPrecio(orden.pagado) }}
        </dd>
        <dt class="os-detalle__saldo">
          Saldo
        </dt>
        <dd class="os-detalle__saldo text-mono">
          {{ formatearPrecio(orden.saldo) }}
        </dd>
      </dl>
    </section>

    <div
      v-if="orden.pagos?.length"
      class="os-detalle__pagos"
    >
      <div
        v-for="pago in orden.pagos"
        :key="pago.id"
        class="os-detalle__linea os-detalle__sub"
      >
        <span>{{ formatearFechaHora(pago.fecha) }} · {{ pago.metodo_label ?? pago.metodo }}<template v-if="pago.usuario"> · {{ pago.usuario.name }}</template></span>
        <span class="text-mono">{{ formatearPrecio(pago.monto) }}</span>
      </div>
    </div>

    <p
      v-if="orden.garantia_hasta"
      class="os-detalle__nota"
    >
      Garantía hasta el <strong>{{ fechaCorta(orden.garantia_hasta) }}</strong>.
    </p>

    <!-- ── Historial ── -->
    <section>
      <h4 class="os-detalle__h">
        Historial
      </h4>
      <ol class="os-detalle__historial">
        <li
          v-for="mov in [...orden.movimientos].reverse()"
          :key="mov.id"
        >
          <AppChip
            :status="ESTADOS[mov.estado]?.status ?? 'info'"
            :label="mov.estado_label"
          />
          <span class="os-detalle__sub">{{ formatearFechaHora(mov.fecha) }}<template v-if="mov.usuario"> · {{ mov.usuario.name }}</template></span>
          <span
            v-if="mov.nota"
            class="os-detalle__movNota"
          >{{ mov.nota }}</span>
        </li>
      </ol>
    </section>
  </div>

  <div
    v-else
    class="os-detalle__cargando"
  >
    <q-spinner size="24px" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import AppChip from '@/components/AppChip.vue'
import OrdenServicioService from '@/services/OrdenServicioService'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearFechaHora } from '@/utils/fechas'
import { formatearPrecio } from '@/utils/moneda'
import { ESTADOS, fechaCorta } from './constantes'

const props = defineProps({
  id: {
    type: Number,
    required: true
  }
})

const orden = ref(null)
const verClave = ref(false)
const puntos = computed(() => Object.entries(orden.value?.checklist ?? {}))

async function cargar () {
  orden.value = await OrdenServicioService.get(props.id)
}

onMounted(cargar)

// Las acciones las dispara la lista (dueña de los diálogos); acá se refresca.
function actualizar (nueva) {
  orden.value = nueva
}

defineExpose({ orden, actualizar, recargar: cargar })
</script>

<style lang="scss" scoped>
.os-detalle {
  display: flex;
  flex-direction: column;
  gap: 14px;
  font-size: 13px;
  color: var(--app-ink);

  h4 {
    margin: 0 0 6px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    color: var(--app-ink-2);
  }

  p {
    margin: 0 0 8px;
    line-height: 1.5;
  }
}

.os-detalle__cabecera {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.os-detalle__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.os-detalle__codigo {
  font-size: 18px;
  font-weight: 700;
}

.os-detalle__meta,
.os-detalle__sub {
  font-size: 12px;
  color: var(--app-ink-2);
}

.os-detalle__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 12px;
}

.os-detalle__card {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px 14px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
}

.os-detalle__fuerte {
  font-weight: 700;
}

.os-detalle__revelar {
  padding: 0;
  border: 0;
  background: none;
  color: $primary-hover;
  cursor: pointer;
}

.os-detalle__checklist {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.os-detalle__punto {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 9px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.os-detalle__punto--ok {
  background: var(--app-tint-positive);
  color: var(--app-ink-positive);
}

.os-detalle__punto--no {
  background: var(--app-border-subtle);
  color: var(--app-ink-2);
}

.os-detalle__cobro {
  display: flex;
  flex-wrap: wrap;
  gap: 16px 24px;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--app-border-subtle);
}

.os-detalle__lineas {
  flex: 1 1 260px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.os-detalle__linea {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.os-detalle__totales {
  display: grid;
  grid-template-columns: auto 120px;
  gap: 4px 16px;
  margin: 0;

  dt {
    color: var(--app-ink-2);
  }

  dd {
    margin: 0;
    text-align: right;
  }
}

.os-detalle__saldo {
  font-size: 16px;
  font-weight: 700;
  color: var(--app-ink) !important;
}

.os-detalle__pagos {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.os-detalle__nota {
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--app-border-subtle);
}

.os-detalle__h {
  margin-bottom: 8px !important;
}

.os-detalle__historial {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;

  li {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
  }
}

.os-detalle__movNota {
  flex-basis: 100%;
  padding-left: 4px;
  font-size: 12.5px;
}

.os-detalle__cargando {
  display: flex;
  justify-content: center;
  padding: 32px;
}
</style>
