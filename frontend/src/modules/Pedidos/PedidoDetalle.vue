<template>
  <div
    v-if="pedido"
    class="pedido-detalle"
  >
    <header class="pedido-detalle__cabecera">
      <div>
        <div class="pedido-detalle__codigo text-mono">
          {{ pedido.codigo }}
        </div>
        <div class="pedido-detalle__meta">
          {{ pedido.canal_label }} · {{ formatearFecha(pedido.fecha) }}
          <template v-if="pedido.usuario">
            · {{ pedido.usuario.name }}
          </template>
        </div>
      </div>
      <AppChip
        :status="ESTADOS[pedido.estado].status"
        :label="ESTADOS[pedido.estado].label"
      />
    </header>

    <section class="pedido-detalle__cliente">
      <q-icon
        name="person"
        size="18px"
      />
      <div v-if="pedido.cliente">
        <div class="pedido-detalle__clienteNombre">
          {{ pedido.cliente.nombre }}
        </div>
        <div class="pedido-detalle__meta">
          <span v-if="pedido.cliente.numero_documento">{{ pedido.cliente.tipo_documento }} {{ pedido.cliente.numero_documento }}</span>
          <span v-if="pedido.cliente.telefono"> · {{ pedido.cliente.telefono }}</span>
        </div>
        <div
          v-if="pedido.cliente.direccion"
          class="pedido-detalle__meta"
        >
          {{ pedido.cliente.direccion }}
        </div>
      </div>
      <div
        v-else
        class="pedido-detalle__meta"
      >
        Cliente varios
      </div>
    </section>

    <ComprobanteBloque
      :pedido="pedido"
      @cambio="recargar"
    />

    <table class="pedido-detalle__tabla">
      <thead>
        <tr>
          <th scope="col">
            Producto
          </th>
          <th
            scope="col"
            class="text-right"
          >
            Cant.
          </th>
          <th
            scope="col"
            class="text-right"
          >
            Precio
          </th>
          <th
            scope="col"
            class="text-right"
          >
            Subtotal
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="item in pedido.items"
          :key="item.id"
        >
          <td>
            <div class="pedido-detalle__producto">
              {{ item.variante.producto?.nombre }}
            </div>
            <div class="pedido-detalle__meta">
              {{ item.variante.presentacion }}<template v-if="item.variante.color"> · {{ item.variante.color.nombre }}</template> ·
              <span class="text-mono">{{ item.variante.sku }}</span>
            </div>
          </td>
          <td class="text-right text-mono">
            {{ item.cantidad }}
          </td>
          <td class="text-right text-mono">
            {{ formatearPrecio(item.precio_unitario) }}
          </td>
          <td class="text-right text-mono">
            {{ formatearPrecio(item.subtotal) }}
          </td>
        </tr>
      </tbody>
    </table>

    <dl class="pedido-detalle__totales">
      <dt>Subtotal</dt>
      <dd class="text-mono">
        {{ formatearPrecio(pedido.subtotal) }}
      </dd>
      <template v-if="Number(pedido.descuento)">
        <dt>Descuento</dt>
        <dd class="text-mono">
          −{{ formatearPrecio(pedido.descuento) }}
        </dd>
      </template>
      <dt>Op. gravada</dt>
      <dd class="text-mono">
        {{ formatearPrecio(desgloseDe(pedido).opGravada) }}
      </dd>
      <dt>{{ ETIQUETA_IGV }}</dt>
      <dd class="text-mono">
        {{ formatearPrecio(desgloseDe(pedido).igv) }}
      </dd>
      <dt class="pedido-detalle__total">
        Total
      </dt>
      <dd class="pedido-detalle__total text-mono">
        {{ formatearPrecio(pedido.total) }}
      </dd>
      <dt>Pagado</dt>
      <dd class="text-mono">
        {{ formatearPrecio(pedido.pagado) }}
      </dd>
      <dt>Saldo</dt>
      <dd :class="['text-mono', Number(pedido.saldo) > 0 ? 'pedido-detalle__saldo' : '']">
        {{ formatearPrecio(pedido.saldo) }}
      </dd>
      <!-- Con el costo congelado al confirmar. -->
      <template v-if="pedido.ganancia !== null">
        <dt>Ganancia</dt>
        <dd :class="['text-mono', Number(pedido.ganancia) < 0 ? 'pedido-detalle__perdida' : 'pedido-detalle__ganancia']">
          {{ formatearPrecio(pedido.ganancia) }}
        </dd>
      </template>
    </dl>

    <section
      v-if="pedido.pagos?.length"
      class="pedido-detalle__pagos"
    >
      <h3 class="pedido-detalle__subtitulo">
        Pagos
      </h3>
      <ul>
        <li
          v-for="pago in pedido.pagos"
          :key="pago.id"
        >
          <div>
            <div class="pedido-detalle__producto">
              {{ pago.es_devolucion ? 'Devolución' : 'Cobro' }} · {{ pago.metodo_label }}
            </div>
            <div class="pedido-detalle__meta">
              {{ formatearFecha(pago.fecha) }}
              <template v-if="pago.referencia">
                · Op. {{ pago.referencia }}
              </template>
              <template v-if="pago.vuelto && Number(pago.vuelto) > 0">
                · recibió {{ formatearPrecio(pago.recibido) }}, vuelto {{ formatearPrecio(pago.vuelto) }}
              </template>
              <template v-if="pago.motivo">
                · {{ pago.motivo }}
              </template>
              <template v-if="pago.usuario">
                · {{ pago.usuario.name }}
              </template>
            </div>
          </div>
          <span :class="['text-mono', pago.es_devolucion ? 'pedido-detalle__perdida' : 'pedido-detalle__ganancia']">
            {{ pago.es_devolucion ? '−' : '+' }}{{ formatearPrecio(Math.abs(Number(pago.monto))) }}
          </span>
        </li>
      </ul>
    </section>

    <p
      v-if="pedido.observacion"
      class="pedido-detalle__observacion"
    >
      {{ pedido.observacion }}
    </p>

    <ol class="pedido-detalle__linea">
      <li>Creado {{ formatearFecha(pedido.fecha) }}</li>
      <li v-if="pedido.confirmado_at">
        Confirmado {{ formatearFecha(pedido.confirmado_at) }} · descontó el stock
      </li>
      <li v-if="pedido.entregado_at">
        Entregado {{ formatearFecha(pedido.entregado_at) }}
      </li>
      <li v-if="pedido.anulado_at">
        Anulado {{ formatearFecha(pedido.anulado_at) }}<template v-if="pedido.anulado_por">
          por {{ pedido.anulado_por.name }}
        </template> · volvieron el stock y la plata<template v-if="pedido.motivo_anulacion">
          · “{{ pedido.motivo_anulacion }}”
        </template>
      </li>
      <li v-if="pedido.cancelado_at">
        Cancelado {{ formatearFecha(pedido.cancelado_at) }}<template v-if="pedido.confirmado_at">
          · el stock volvió al inventario
        </template>
      </li>
    </ol>
  </div>

  <div
    v-else
    class="pedido-detalle__cargando"
  >
    <q-spinner size="24px" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import AppChip from '@/components/AppChip.vue'
import ComprobanteBloque from '@/modules/Comprobantes/ComprobanteBloque.vue'
import PedidoService from '@/services/PedidoService'
import { formatearPrecio } from '@/utils/moneda'
import { desgloseDe, ETIQUETA_IGV } from '@/utils/igv'
import { ESTADOS } from './constantes'

const props = defineProps({
  id: {
    type: Number,
    required: true
  }
})

const pedido = ref(null)

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })
function formatearFecha (iso) {
  return iso ? formatoFecha.format(new Date(iso)) : ''
}

onMounted(async () => {
  pedido.value = await PedidoService.get(props.id)
})

// Las acciones las dispara la lista (dueña de los diálogos); acá se refresca.
function actualizar (nuevo) {
  pedido.value = nuevo
}

// Tras emitir o reenviar el comprobante (lo hace el bloque, acá mismo).
async function recargar () {
  pedido.value = await PedidoService.get(props.id)
}

defineExpose({ pedido, actualizar })
</script>

<style lang="scss" scoped>
.pedido-detalle {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.pedido-detalle__cabecera {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.pedido-detalle__codigo {
  font-size: 18px;
  font-weight: 700;
  color: var(--app-ink);
}

.pedido-detalle__meta {
  font-size: 12px;
  color: var(--app-ink-2);
}

.pedido-detalle__cliente {
  display: flex;
  gap: 10px;
  padding: 12px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
  color: var(--app-ink-2);
}

.pedido-detalle__clienteNombre,
.pedido-detalle__producto {
  font-weight: 600;
  color: var(--app-ink);
}

.pedido-detalle__tabla {
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
    padding: 8px 6px;
    vertical-align: top;
    border-top: 1px solid var(--app-border-subtle);
    font-size: 13px;
    color: var(--app-ink);
  }

  .text-right {
    text-align: right;
  }
}

.pedido-detalle__totales {
  display: grid;
  grid-template-columns: auto 140px;
  gap: 6px 16px;
  justify-content: end;
  margin: 0;
  font-size: 13px;

  dt {
    color: var(--app-ink-2);
  }

  dd {
    margin: 0;
    text-align: right;
    color: var(--app-ink);
  }
}

.pedido-detalle__total {
  padding-top: 6px;
  border-top: 1px solid var(--app-border-subtle);
  font-size: 16px;
  font-weight: 700;
  color: var(--app-ink) !important;
}

.pedido-detalle__ganancia {
  font-weight: 600;
  color: var(--q-positive) !important;
}

.pedido-detalle__perdida {
  font-weight: 600;
  color: var(--q-negative) !important;
}

.pedido-detalle__saldo {
  font-weight: 600;
  color: var(--q-warning) !important;
}

.pedido-detalle__subtitulo {
  margin: 0 0 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.pedido-detalle__pagos ul {
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
  }
}

.pedido-detalle__observacion {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--app-border-subtle);
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink);
  white-space: pre-line;
}

.pedido-detalle__linea {
  margin: 0;
  padding-left: 18px;
  font-size: 12px;
  line-height: 1.8;
  color: var(--app-ink-2);
}

.pedido-detalle__cargando {
  display: flex;
  justify-content: center;
  padding: 32px;
}
</style>
