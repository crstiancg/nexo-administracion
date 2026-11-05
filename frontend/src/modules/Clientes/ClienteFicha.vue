<template>
  <div
    v-if="ficha"
    class="ficha"
  >
    <!-- ── Quién es ── -->
    <header class="ficha__cabecera">
      <AppAvatar
        :nombre="ficha.cliente.nombre"
        :size="48"
      />
      <div class="ficha__quien">
        <div class="ficha__nombre">
          {{ ficha.cliente.nombre }}
          <AppChip
            v-if="ficha.cliente.mayorista"
            status="info"
            label="Mayorista"
          />
        </div>
        <div class="ficha__contacto">
          <span v-if="ficha.cliente.numero_documento">
            {{ ficha.cliente.tipo_documento }} <span class="text-mono">{{ ficha.cliente.numero_documento }}</span>
          </span>
          <a
            v-if="ficha.cliente.telefono"
            :href="whatsapp ?? `tel:${ficha.cliente.telefono}`"
            :target="whatsapp ? '_blank' : undefined"
            rel="noopener"
          >
            <q-icon :name="whatsapp ? 'mdi-whatsapp' : 'call'" /> {{ ficha.cliente.telefono }}
          </a>
          <span v-if="ficha.cliente.email">{{ ficha.cliente.email }}</span>
          <span v-if="ficha.cliente.direccion">{{ ficha.cliente.direccion }}</span>
        </div>
      </div>
    </header>

    <!-- ── En números ── -->
    <dl class="ficha__resumen">
      <div>
        <dt>Compras</dt>
        <dd class="text-mono">
          {{ ficha.resumen.compras }}
        </dd>
      </div>
      <div>
        <dt>Total comprado</dt>
        <dd class="text-mono">
          {{ formatearPrecio(ficha.resumen.total_comprado) }}
        </dd>
      </div>
      <div :class="{ 'ficha__debe': ficha.resumen.debe > 0 }">
        <dt>Debe</dt>
        <dd class="text-mono">
          {{ formatearPrecio(ficha.resumen.debe) }}
        </dd>
      </div>
      <div>
        <dt>Última compra</dt>
        <dd>{{ ficha.resumen.ultima_compra ? fechaCorta(ficha.resumen.ultima_compra) : '—' }}</dd>
      </div>
    </dl>

    <!-- ── Créditos con saldo ── -->
    <section
      v-if="ficha.creditos?.length"
      class="ficha__seccion"
    >
      <h3 class="ficha__titulo">
        Créditos con saldo
      </h3>
      <router-link
        v-for="c in ficha.creditos"
        :key="c.id"
        to="/creditos"
        class="ficha__fila"
      >
        <span>
          <strong class="text-mono">{{ c.pedido }}</strong>
          <span class="ficha__muted">
            <template v-if="c.cuotas_vencidas">{{ c.cuotas_vencidas }} {{ c.cuotas_vencidas === 1 ? 'cuota vencida' : 'cuotas vencidas' }} · </template>
            <template v-if="c.proxima">próxima el {{ fechaCorta(c.proxima.vence) }}</template>
          </span>
        </span>
        <span class="text-mono ficha__monto">{{ formatearPrecio(c.saldo) }}</span>
      </router-link>
    </section>

    <!-- ── Compras ── -->
    <section
      v-if="ficha.pedidos"
      class="ficha__seccion"
    >
      <h3 class="ficha__titulo">
        Pedidos
        <span class="ficha__muted">{{ ficha.resumen.compras }} compras</span>
      </h3>
      <p
        v-if="!ficha.pedidos.length"
        class="ficha__muted"
      >
        Todavía no compró nada.
      </p>
      <router-link
        v-for="p in ficha.pedidos"
        :key="p.id"
        :to="`/pedidos?ver=${p.id}`"
        class="ficha__fila"
      >
        <span>
          <strong class="text-mono">{{ p.codigo }}</strong>
          <span class="ficha__muted">{{ fechaCorta(p.fecha) }} · {{ p.canal }}</span>
        </span>
        <span class="ficha__derecha">
          <AppChip
            :status="ESTADOS_PEDIDO[p.estado]?.status ?? 'info'"
            :label="ESTADOS_PEDIDO[p.estado]?.label ?? p.estado"
          />
          <span class="text-mono ficha__monto">
            {{ formatearPrecio(p.total) }}
            <small
              v-if="Number(p.saldo) > 0"
              class="ficha__saldo"
            >debe {{ formatearPrecio(p.saldo) }}</small>
          </span>
        </span>
      </router-link>
    </section>

    <!-- ── Taller ── -->
    <section
      v-if="ficha.ordenes"
      class="ficha__seccion"
    >
      <h3 class="ficha__titulo">
        Órdenes de servicio
        <span class="ficha__muted">{{ ficha.resumen.ordenes }}</span>
      </h3>
      <p
        v-if="!ficha.ordenes.length"
        class="ficha__muted"
      >
        No trajo equipos al taller.
      </p>
      <router-link
        v-for="o in ficha.ordenes"
        :key="o.id"
        :to="`/ordenes-servicio?ver=${o.id}`"
        class="ficha__fila"
      >
        <span>
          <strong class="text-mono">{{ o.codigo }}</strong>
          <span class="ficha__muted">{{ fechaCorta(o.fecha) }} · {{ o.equipo }}</span>
        </span>
        <AppChip
          :status="ESTADOS_OS[o.estado]?.status ?? 'info'"
          :label="o.estado_label"
        />
      </router-link>
    </section>

    <!-- ── Cotizaciones ── -->
    <section
      v-if="ficha.cotizaciones"
      class="ficha__seccion"
    >
      <h3 class="ficha__titulo">
        Cotizaciones
        <span class="ficha__muted">{{ ficha.resumen.cotizaciones }}</span>
      </h3>
      <p
        v-if="!ficha.cotizaciones.length"
        class="ficha__muted"
      >
        No tiene cotizaciones.
      </p>
      <router-link
        v-for="c in ficha.cotizaciones"
        :key="c.id"
        :to="`/cotizaciones?ver=${c.id}`"
        class="ficha__fila"
      >
        <span>
          <strong class="text-mono">{{ c.codigo }}</strong>
          <span class="ficha__muted">
            {{ fechaCorta(c.fecha) }}<template v-if="c.vistas"> · <q-icon name="visibility" /> {{ c.vistas }}</template>
          </span>
        </span>
        <span class="ficha__derecha">
          <AppChip
            :status="ESTADOS_COTIZACION[c.estado]?.status ?? 'info'"
            :label="ESTADOS_COTIZACION[c.estado]?.label ?? c.estado"
          />
          <span class="text-mono ficha__monto">{{ formatearPrecio(c.total) }}</span>
        </span>
      </router-link>
    </section>
  </div>

  <div
    v-else
    class="ficha__cargando"
  >
    <q-spinner size="24px" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import AppAvatar from '@/components/AppAvatar.vue'
import AppChip from '@/components/AppChip.vue'
import { ESTADOS as ESTADOS_COTIZACION, fechaCorta, numeroWhatsapp } from '@/modules/Cotizaciones/constantes'
import { ESTADOS as ESTADOS_OS } from '@/modules/OrdenesServicio/constantes'
import { ESTADOS as ESTADOS_PEDIDO } from '@/modules/Pedidos/constantes'
import ClienteService from '@/services/ClienteService'
import { formatearPrecio } from '@/utils/moneda'

/**
 * Todo lo del cliente en una pantalla: compras, taller, cotizaciones, créditos
 * y cuánto debe. Cada sección viene sólo si el usuario ve ese módulo; cada
 * fila abre el registro en su módulo.
 */
const props = defineProps({
  id: {
    type: Number,
    required: true
  }
})

const ficha = ref(null)

const whatsapp = computed(() => {
  const numero = numeroWhatsapp(ficha.value?.cliente.telefono)
  return numero ? `https://wa.me/${numero}` : null
})

onMounted(async () => {
  ficha.value = await ClienteService.ficha(props.id)
})
</script>

<style lang="scss" scoped>
.ficha {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.ficha__cargando {
  display: flex;
  justify-content: center;
  padding: 32px;
}

.ficha__cabecera {
  display: flex;
  align-items: center;
  gap: 14px;
}

.ficha__quien {
  min-width: 0;
}

.ficha__nombre {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 18px;
  font-weight: 700;
  color: var(--app-ink);
}

.ficha__contacto {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 14px;
  margin-top: 4px;
  font-size: 12.5px;
  color: var(--app-ink-2);

  a {
    color: var(--q-positive);
    text-decoration: none;
  }
}

.ficha__resumen {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 10px;
  margin: 0;

  > div {
    padding: 10px 12px;
    border: 1px solid var(--app-border-subtle);
    border-radius: 10px;
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
    font-size: 16px;
    font-weight: 700;
    color: var(--app-ink);
  }
}

.ficha__debe dd {
  color: var(--q-negative);
}

.ficha__seccion {
  display: flex;
  flex-direction: column;
}

.ficha__titulo {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin: 0 0 6px;
  font-size: 14px;
  font-weight: 700;
  color: var(--app-ink);
}

.ficha__muted {
  font-size: 12px;
  font-weight: 400;
  color: var(--app-ink-2);
}

.ficha__fila {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 4px;
  border-bottom: 1px solid var(--app-border-subtle);
  color: var(--app-ink);
  text-decoration: none;

  > span:first-child {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
  }

  &:hover {
    background: var(--app-brand-soft);
  }
}

.ficha__derecha {
  display: flex;
  align-items: center;
  gap: 10px;
}

.ficha__monto {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  font-size: 13px;
}

.ficha__saldo {
  font-size: 11px;
  color: var(--q-negative);
}
</style>
