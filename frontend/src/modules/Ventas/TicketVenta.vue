<template>
  <div class="ticket">
    <header class="ticket__centro">
      <div class="ticket__tienda">
        {{ TIENDA.nombre }}
      </div>
      <!-- Con comprobante electrónico, el RUC es el que se mandó a SUNAT. -->
      <div v-if="pedido.comprobante?.emisor?.ruc || TIENDA.ruc">
        RUC {{ pedido.comprobante?.emisor?.ruc || TIENDA.ruc }}
      </div>
      <!-- La dirección y el teléfono de la sede que vendió; si no los
           tiene cargados, los generales de la tienda. -->
      <div v-if="pedido.sede">
        {{ pedido.sede.nombre }}
      </div>
      <div v-if="pedido.sede?.direccion || TIENDA.direccion">
        {{ pedido.sede?.direccion || TIENDA.direccion }}
      </div>
      <div v-if="pedido.sede?.telefono || TIENDA.telefono">
        Tel. {{ pedido.sede?.telefono || TIENDA.telefono }}
      </div>
    </header>

    <div class="ticket__separador" />

    <!-- Con boleta o factura: es su representación impresa. Sin ella, un
         ticket interno (no es comprobante de pago). -->
    <template v-if="electronico">
      <div class="ticket__centro ticket__tipo">
        {{ electronico.tipo === '01' ? 'FACTURA ELECTRÓNICA' : 'BOLETA DE VENTA ELECTRÓNICA' }}
      </div>
      <div class="ticket__centro ticket__tipo">
        {{ electronico.numero }}
      </div>
      <div class="ticket__centro">
        Pedido {{ pedido.codigo }}
      </div>
    </template>
    <template v-else>
      <div class="ticket__centro ticket__tipo">
        TICKET DE VENTA {{ pedido.codigo }}
      </div>
      <div class="ticket__centro">
        Solicite su comprobante de pago
      </div>
    </template>

    <div class="ticket__separador" />

    <div>{{ formatearFecha(pedido.confirmado_at ?? pedido.fecha) }}</div>
    <div v-if="pedido.usuario">
      Atendió: {{ pedido.usuario.name }}
    </div>
    <!-- El cliente tal como se mandó a SUNAT (documento incluido). -->
    <div v-if="electronico">
      {{ electronico.tipo === '01' ? 'Razón social' : 'Cliente' }}: {{ electronico.cliente.nombre }}
      <div v-if="electronico.cliente.num_doc && electronico.cliente.num_doc !== '-'">
        {{ TIPOS_DOC[electronico.cliente.tipo_doc] ?? 'Doc.' }} {{ electronico.cliente.num_doc }}
      </div>
    </div>
    <div v-else-if="pedido.cliente">
      Cliente: {{ pedido.cliente.nombre }}
      <template v-if="pedido.cliente.numero_documento">
        ({{ pedido.cliente.tipo_documento }} {{ pedido.cliente.numero_documento }})
      </template>
    </div>

    <div class="ticket__separador" />

    <div
      v-for="item in pedido.items"
      :key="item.id"
      class="ticket__item"
    >
      <div>
        {{ item.variante.producto?.nombre }} {{ item.variante.presentacion }} {{ item.variante.color?.nombre }}
      </div>
      <div class="ticket__fila">
        <span>{{ item.cantidad }} x {{ formatearPrecio(item.precio_unitario) }}</span>
        <span>{{ formatearPrecio(item.subtotal) }}</span>
      </div>
    </div>

    <div class="ticket__separador" />

    <div
      v-if="Number(pedido.descuento)"
      class="ticket__fila"
    >
      <span>Subtotal</span>
      <span>{{ formatearPrecio(pedido.subtotal) }}</span>
    </div>
    <div
      v-if="Number(pedido.descuento)"
      class="ticket__fila"
    >
      <span>Descuento</span>
      <span>-{{ formatearPrecio(pedido.descuento) }}</span>
    </div>
    <div class="ticket__fila">
      <span>Op. gravada</span>
      <span>{{ formatearPrecio(desgloseDe(pedido).opGravada) }}</span>
    </div>
    <div class="ticket__fila">
      <span>{{ ETIQUETA_IGV }}</span>
      <span>{{ formatearPrecio(desgloseDe(pedido).igv) }}</span>
    </div>
    <div class="ticket__fila ticket__total">
      <span>TOTAL</span>
      <span>{{ formatearPrecio(pedido.total) }}</span>
    </div>

    <div class="ticket__separador" />

    <template
      v-for="pago in pagosCobro"
      :key="pago.id"
    >
      <div class="ticket__fila">
        <span>{{ pago.metodo_label }}<template v-if="pago.referencia"> ({{ pago.referencia }})</template></span>
        <span>{{ formatearPrecio(pago.recibido ?? pago.monto) }}</span>
      </div>
      <div
        v-if="Number(pago.vuelto) > 0"
        class="ticket__fila"
      >
        <span>Vuelto</span>
        <span>{{ formatearPrecio(pago.vuelto) }}</span>
      </div>
    </template>

    <template v-if="electronico">
      <div class="ticket__letras">
        {{ electronico.total_letras }}
      </div>
      <div class="ticket__separador" />
      <div class="ticket__centro ticket__chico">
        Representación impresa de la {{ electronico.tipo === '01' ? 'Factura Electrónica' : 'Boleta de Venta Electrónica' }}.
        Consulte su validez en www.sunat.gob.pe
      </div>
      <!-- SVG armado por el backend (bacon-qr-code): sólo rutas, sin texto
           tipeado por nadie, por eso va con v-html. -->
      <div
        v-if="electronico.qr_svg"
        class="ticket__qr"
        v-html="electronico.qr_svg"
      />
      <div
        v-if="electronico.hash"
        class="ticket__centro ticket__chico"
      >
        Hash: {{ electronico.hash }}
      </div>
      <div
        v-if="electronico.estado !== 'aceptado'"
        class="ticket__centro ticket__chico"
      >
        (Pendiente de envío a SUNAT)
      </div>
    </template>

    <div class="ticket__separador" />

    <div class="ticket__centro">
      {{ TIENDA.pie }}
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { TIENDA } from '@/config/tienda'
import { formatearPrecio } from '@/utils/moneda'
import { desgloseDe, ETIQUETA_IGV } from '@/utils/igv'

/**
 * El ticket de 80 mm, tal cual sale impreso (también sirve de vista previa).
 * Recibe un PedidoResource con ítems y pagos.
 */
const props = defineProps({
  pedido: {
    type: Object,
    required: true
  }
})

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })
function formatearFecha (iso) {
  return iso ? formatoFecha.format(new Date(iso)) : ''
}

// El comprobante electrónico vigente (un rechazado no se imprime como tal).
const electronico = computed(() => {
  const c = props.pedido.comprobante
  return c && c.estado !== 'rechazado' ? c : null
})

// Catálogo 06 de SUNAT.
const TIPOS_DOC = { 1: 'DNI', 4: 'C.E.', 6: 'RUC' }

// Las devoluciones no van en el ticket de la venta.
const pagosCobro = computed(() => (props.pedido.pagos ?? []).filter((p) => !p.es_devolucion))
</script>

<style lang="scss" scoped>
// 80 mm de papel ≈ 72 mm imprimibles. Negro puro y monoespaciada: las
// ticketeras térmicas no imprimen grises y así las columnas se alinean.
.ticket {
  // El driver suele dar los 72 mm imprimibles como ancho de página: con
  // 100% + padding el texto no queda pegado al filo del papel.
  box-sizing: border-box;
  width: 100%;
  max-width: 72mm;
  margin: 0 auto;
  padding: 4mm 2mm;
  background: #FFFFFF;
  color: #000000;
  font-family: $font-mono;
  font-size: 11.5px;
  line-height: 1.35;
}

.ticket__centro {
  text-align: center;
}

.ticket__tienda {
  font-size: 15px;
  font-weight: 700;
}

.ticket__tipo {
  font-weight: 700;
}

.ticket__separador {
  margin: 6px 0;
  border-top: 1px dashed #000000;
}

.ticket__item + .ticket__item {
  margin-top: 4px;
}

.ticket__fila {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}

.ticket__letras {
  margin-top: 4px;
  font-size: 10.5px;
  font-weight: 700;
}

.ticket__chico {
  font-size: 10px;
  word-break: break-all;
}

.ticket__qr {
  width: 30mm;
  margin: 6px auto 4px;

  :deep(svg) {
    display: block;
    width: 100%;
    height: auto;
  }
}

.ticket__total {
  font-size: 14px;
  font-weight: 700;
}
</style>
