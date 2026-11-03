<template>
  <!-- La orden tal como se imprime (A4) o se guarda en PDF para mandársela
       al proveedor. Blanco y negro a propósito. -->
  <article class="oc-doc">
    <header class="oc-doc__cabecera">
      <div>
        <div class="oc-doc__tienda">
          {{ TIENDA.nombre }}
        </div>
        <div v-if="TIENDA.ruc">
          RUC {{ TIENDA.ruc }}
        </div>
        <div v-if="orden.sede?.direccion || TIENDA.direccion">
          {{ orden.sede?.direccion || TIENDA.direccion }}
        </div>
        <div v-if="orden.sede?.telefono || TIENDA.telefono">
          Tel. {{ orden.sede?.telefono || TIENDA.telefono }}
        </div>
      </div>
      <div class="oc-doc__caja">
        <div class="oc-doc__titulo">
          PEDIDO DE COMPRA
        </div>
        <div class="oc-doc__codigo">
          {{ orden.codigo }}
        </div>
        <div>Fecha: {{ fechaCorta(orden.fecha) }}</div>
        <div v-if="orden.fecha_entrega">
          Entrega: {{ fechaCorta(orden.fecha_entrega) }}
        </div>
      </div>
    </header>

    <section class="oc-doc__proveedor">
      <div><strong>Proveedor:</strong> {{ orden.proveedor?.razon_social }}</div>
      <div v-if="orden.proveedor?.ruc">
        <strong>RUC:</strong> {{ orden.proveedor.ruc }}
      </div>
      <div v-if="orden.proveedor?.contacto">
        <strong>Atención:</strong> {{ orden.proveedor.contacto }}
      </div>
      <div v-if="orden.proveedor?.direccion">
        <strong>Dirección:</strong> {{ orden.proveedor.direccion }}
      </div>
      <div v-if="orden.sede">
        <strong>Entregar en:</strong> {{ orden.sede.nombre }}<template v-if="orden.sede.direccion">
          — {{ orden.sede.direccion }}
        </template>
      </div>
    </section>

    <table class="oc-doc__tabla">
      <thead>
        <tr>
          <th>#</th>
          <th>Descripción</th>
          <th class="oc-doc__num">
            Cant.
          </th>
          <th>Und.</th>
          <th class="oc-doc__num">
            P. unit.
          </th>
          <th class="oc-doc__num">
            Importe
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(item, i) in orden.items"
          :key="item.id"
        >
          <td>{{ i + 1 }}</td>
          <td>
            {{ item.variante.producto?.nombre }} — {{ item.variante.presentacion }}<template v-if="item.variante.color">
              {{ item.variante.color.nombre }}
            </template>
            <div class="oc-doc__sku">
              {{ item.variante.sku }}
            </div>
          </td>
          <td class="oc-doc__num">
            {{ formatearCantidad(item.cantidad) }}
          </td>
          <td>{{ item.variante.unidad?.abreviatura }}</td>
          <td class="oc-doc__num">
            {{ item.costo_unitario !== null ? formatearPrecio(item.costo_unitario) : '—' }}
          </td>
          <td class="oc-doc__num">
            {{ item.costo_unitario !== null ? formatearPrecio(item.subtotal) : '—' }}
          </td>
        </tr>
      </tbody>
    </table>

    <dl
      v-if="Number(orden.total)"
      class="oc-doc__totales"
    >
      <dt>Op. gravada</dt>
      <dd>{{ formatearPrecio(desgloseDe(orden).opGravada) }}</dd>
      <dt>{{ ETIQUETA_IGV }}</dt>
      <dd>{{ formatearPrecio(desgloseDe(orden).igv) }}</dd>
      <dt class="oc-doc__total">
        TOTAL
      </dt>
      <dd class="oc-doc__total">
        {{ formatearPrecio(orden.total) }}
      </dd>
    </dl>

    <section
      v-if="orden.condiciones"
      class="oc-doc__condiciones"
    >
      <strong>Condiciones</strong>
      <p>{{ orden.condiciones }}</p>
    </section>

    <footer class="oc-doc__pie">
      <span v-if="orden.usuario">Solicitado por: {{ orden.usuario.name }}</span>
      <span>Favor de indicar el n° de pedido en la guía y la factura.</span>
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
  // OrdenCompraResource con ítems.
  orden: {
    type: Object,
    required: true
  }
})
</script>

<style lang="scss" scoped>
.oc-doc {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 11pt;
  line-height: 1.4;
  color: #000000;
  background: #FFFFFF;
}

.oc-doc__cabecera {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid #000000;
}

.oc-doc__tienda {
  font-size: 18pt;
  font-weight: 700;
}

.oc-doc__caja {
  min-width: 200px;
  padding: 8px 12px;
  border: 1px solid #000000;
  text-align: center;
}

.oc-doc__titulo {
  font-size: 13pt;
  font-weight: 700;
  letter-spacing: 1px;
}

.oc-doc__codigo {
  font-family: monospace;
  font-size: 13pt;
  font-weight: 700;
}

.oc-doc__proveedor {
  margin: 14px 0;
}

.oc-doc__tabla {
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

.oc-doc__num {
  text-align: right !important;
  white-space: nowrap;
}

.oc-doc__sku {
  font-family: monospace;
  font-size: 9pt;
}

.oc-doc__totales {
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

.oc-doc__total {
  font-size: 13pt;
  font-weight: 700;
}

.oc-doc__condiciones {
  margin-top: 18px;

  p {
    margin: 4px 0 0;
    white-space: pre-line;
  }
}

.oc-doc__pie {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  margin-top: 28px;
  padding-top: 8px;
  border-top: 1px solid #000000;
  font-size: 9pt;
}
</style>
