<template>
  <!-- La cotización tal como se imprime (A4) o se guarda en PDF desde el
       diálogo de impresión del navegador. Blanco y negro a propósito. -->
  <article class="cot-doc">
    <header class="cot-doc__cabecera">
      <div>
        <div class="cot-doc__tienda">
          {{ TIENDA.nombre }}
        </div>
        <div v-if="TIENDA.ruc">
          RUC {{ TIENDA.ruc }}
        </div>
        <div v-if="cotizacion.sede?.direccion || TIENDA.direccion">
          {{ cotizacion.sede?.direccion || TIENDA.direccion }}
        </div>
        <div v-if="cotizacion.sede?.telefono || TIENDA.telefono">
          Tel. {{ cotizacion.sede?.telefono || TIENDA.telefono }}
        </div>
      </div>
      <div class="cot-doc__caja">
        <div class="cot-doc__titulo">
          COTIZACIÓN
        </div>
        <div class="cot-doc__codigo">
          {{ cotizacion.codigo }}
        </div>
        <div>Fecha: {{ fechaCorta(cotizacion.fecha) }}</div>
        <div>Válida hasta: {{ fechaCorta(cotizacion.valida_hasta) }}</div>
      </div>
    </header>

    <section class="cot-doc__cliente">
      <!-- Sin cliente asignado: "Clientes varios", como en las ventas sin nombre. -->
      <div>
        <strong>Cliente:</strong> {{ cotizacion.cliente?.nombre ?? 'Clientes varios' }}
      </div>
      <div v-if="cotizacion.cliente?.numero_documento">
        <strong>{{ cotizacion.cliente.tipo_documento }}:</strong> {{ cotizacion.cliente.numero_documento }}
      </div>
      <div v-if="cotizacion.cliente?.direccion">
        <strong>Dirección:</strong> {{ cotizacion.cliente.direccion }}
      </div>
      <div v-if="cotizacion.cliente?.telefono">
        <strong>Teléfono:</strong> {{ cotizacion.cliente.telefono }}
      </div>
    </section>

    <table class="cot-doc__tabla">
      <thead>
        <tr>
          <th>#</th>
          <th>Descripción</th>
          <th class="cot-doc__num">
            Cant.
          </th>
          <th>Und.</th>
          <th class="cot-doc__num">
            P. unit.
          </th>
          <th class="cot-doc__num">
            Importe
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(item, i) in cotizacion.items"
          :key="item.id"
        >
          <td>{{ i + 1 }}</td>
          <td>
            {{ item.variante.producto?.nombre }} — {{ item.variante.presentacion }}<template v-if="item.variante.color">
              {{ item.variante.color.nombre }}
            </template>
            <div class="cot-doc__sku">
              {{ item.variante.sku }}
            </div>
          </td>
          <td class="cot-doc__num">
            {{ formatearCantidad(item.cantidad) }}
          </td>
          <td>{{ item.variante.unidad?.abreviatura }}</td>
          <td class="cot-doc__num">
            {{ formatearPrecio(item.precio_unitario) }}
          </td>
          <td class="cot-doc__num">
            {{ formatearPrecio(item.subtotal) }}
          </td>
        </tr>
      </tbody>
    </table>

    <dl class="cot-doc__totales">
      <template v-if="Number(cotizacion.descuento)">
        <dt>Subtotal</dt>
        <dd>{{ formatearPrecio(cotizacion.subtotal) }}</dd>
        <dt>Descuento</dt>
        <dd>−{{ formatearPrecio(cotizacion.descuento) }}</dd>
      </template>
      <dt>Op. gravada</dt>
      <dd>{{ formatearPrecio(desgloseDe(cotizacion).opGravada) }}</dd>
      <dt>{{ ETIQUETA_IGV }}</dt>
      <dd>{{ formatearPrecio(desgloseDe(cotizacion).igv) }}</dd>
      <dt class="cot-doc__total">
        TOTAL
      </dt>
      <dd class="cot-doc__total">
        {{ formatearPrecio(cotizacion.total) }}
      </dd>
    </dl>

    <section
      v-if="cotizacion.condiciones"
      class="cot-doc__condiciones"
    >
      <strong>Condiciones</strong>
      <p>{{ cotizacion.condiciones }}</p>
    </section>

    <footer class="cot-doc__pie">
      <span v-if="cotizacion.usuario">Atendido por: {{ cotizacion.usuario.name }}</span>
      <span>Precios sujetos a disponibilidad de stock al momento del pedido.</span>
    </footer>
  </article>
</template>

<script setup>
import { TIENDA } from '@/config/tienda'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'
import { desgloseDe, ETIQUETA_IGV } from '@/utils/igv'
import { fechaCorta } from './constantes'

defineProps({
  // CotizacionResource con ítems.
  cotizacion: {
    type: Object,
    required: true
  }
})
</script>

<style lang="scss" scoped>
.cot-doc {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 11pt;
  line-height: 1.4;
  color: #000000;
  background: #FFFFFF;
}

.cot-doc__cabecera {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid #000000;
}

.cot-doc__tienda {
  font-size: 18pt;
  font-weight: 700;
}

.cot-doc__caja {
  min-width: 200px;
  padding: 8px 12px;
  border: 1px solid #000000;
  text-align: center;
}

.cot-doc__titulo {
  font-size: 13pt;
  font-weight: 700;
  letter-spacing: 1px;
}

.cot-doc__codigo {
  font-family: monospace;
  font-size: 13pt;
  font-weight: 700;
}

.cot-doc__cliente {
  margin: 14px 0;
}

.cot-doc__tabla {
  width: 100%;
  border-collapse: collapse;

  th,
  td {
    padding: 5px 6px;
    border: 1px solid #000000;
    text-align: left;
    vertical-align: top;
  }

  th {
    background: #EEEEEE;
    font-size: 10pt;
  }

  tr {
    break-inside: avoid;
  }
}

.cot-doc__num {
  text-align: right !important;
  white-space: nowrap;
}

.cot-doc__sku {
  font-family: monospace;
  font-size: 9pt;
}

.cot-doc__totales {
  display: grid;
  grid-template-columns: auto 130px;
  justify-content: end;
  gap: 4px 16px;
  margin: 10px 0 0;

  dd {
    margin: 0;
    text-align: right;
  }
}

.cot-doc__total {
  font-size: 13pt;
  font-weight: 700;
}

.cot-doc__condiciones {
  margin-top: 18px;

  p {
    margin: 4px 0 0;
    white-space: pre-line;
  }
}

.cot-doc__pie {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  margin-top: 28px;
  padding-top: 8px;
  border-top: 1px solid #000000;
  font-size: 9pt;
}
</style>
