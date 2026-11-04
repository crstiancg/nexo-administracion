<template>
  <!-- La proforma tal como se imprime (A4) o se guarda en PDF desde el
       diálogo de impresión del navegador. Con los colores de la marca:
       print-color-adjust los conserva al imprimir. -->
  <article class="cot-doc">
    <!-- ── Membrete ── -->
    <header class="cot-doc__membrete">
      <img
        :src="logo"
        alt="Nexo Technology"
        class="cot-doc__logo"
      >
      <div class="cot-doc__centro">
        <p class="cot-doc__lema">
          {{ TIENDA.lema }}
        </p>
        <p
          v-if="cotizacion.sede?.horario"
          class="cot-doc__horario"
        >
          Horario de atención: {{ cotizacion.sede.horario }}
        </p>
      </div>
      <div class="cot-doc__caja">
        <div class="cot-doc__ruc">
          RUC {{ TIENDA.ruc }}
        </div>
        <div class="cot-doc__titulo">
          PROFORMA
        </div>
        <div class="cot-doc__codigo">
          N° {{ cotizacion.codigo }}
        </div>
      </div>
    </header>

    <div class="cot-doc__contacto">
      <span>{{ cotizacion.sede?.direccion || TIENDA.direccion }}</span>
      <span>Contacto: {{ cotizacion.sede?.telefono || TIENDA.telefono }}</span>
    </div>

    <!-- ── Cliente ── -->
    <section class="cot-doc__bloque">
      <h3 class="cot-doc__banda">
        Datos del cliente
      </h3>
      <div class="cot-doc__cliente">
        <dl>
          <dt>Nombre o razón social</dt>
          <dd class="cot-doc__fuerte">
            {{ cotizacion.cliente?.nombre ?? 'Clientes varios' }}
          </dd>
          <dt>Dirección</dt>
          <dd>{{ cotizacion.cliente?.direccion || '—' }}</dd>
          <template v-if="cotizacion.cliente?.telefono">
            <dt>Teléfono</dt>
            <dd>{{ cotizacion.cliente.telefono }}</dd>
          </template>
        </dl>
        <dl>
          <dt>{{ cotizacion.cliente?.tipo_documento || 'DNI/RUC' }}</dt>
          <dd class="cot-doc__fuerte">
            {{ cotizacion.cliente?.numero_documento || '—' }}
          </dd>
          <dt>Fecha</dt>
          <dd>{{ fechaCorta(cotizacion.fecha) }}</dd>
          <template v-if="cotizacion.usuario">
            <dt>Atendido por</dt>
            <dd>{{ cotizacion.usuario.name }}</dd>
          </template>
        </dl>
      </div>
    </section>

    <!-- ── Detalle ── -->
    <table class="cot-doc__tabla">
      <thead>
        <tr>
          <th class="cot-doc__cant">
            Cant.
          </th>
          <th>Descripción</th>
          <th class="cot-doc__num">
            P. unitario
          </th>
          <th class="cot-doc__num">
            P. total
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="item in cotizacion.items"
          :key="item.id"
        >
          <td class="cot-doc__cant">
            {{ formatearCantidad(item.cantidad) }}
            <span class="cot-doc__und">{{ item.variante.unidad?.abreviatura }}</span>
          </td>
          <td>
            <div class="cot-doc__producto">
              {{ descripcion(item) }}
            </div>
            <div class="cot-doc__sku">
              {{ item.variante.sku }}
            </div>
          </td>
          <td class="cot-doc__num">
            {{ formatearPrecio(item.precio_unitario) }}
          </td>
          <td class="cot-doc__num">
            {{ formatearPrecio(item.subtotal) }}
          </td>
        </tr>
      </tbody>
    </table>

    <!-- ── Totales ── -->
    <div class="cot-doc__resumen">
      <p class="cot-doc__letras">
        {{ cotizacion.total_letras }}
      </p>
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
          Total
        </dt>
        <dd class="cot-doc__total">
          {{ formatearPrecio(cotizacion.total) }}
        </dd>
      </dl>
    </div>
    <p class="cot-doc__igv">
      Precios incluyen IGV.
    </p>

    <!-- ── Condiciones ── -->
    <table class="cot-doc__condiciones">
      <tbody>
        <tr>
          <th>Forma de pago</th>
          <td>{{ cotizacion.forma_pago || 'Contado' }}</td>
        </tr>
        <tr>
          <th>Validez de oferta</th>
          <td>{{ validez }}</td>
        </tr>
        <tr v-if="cotizacion.entrega">
          <th>Entrega</th>
          <td>{{ cotizacion.entrega }}</td>
        </tr>
        <tr v-if="cotizacion.condiciones">
          <th>Condiciones</th>
          <td class="cot-doc__multilinea">
            {{ cotizacion.condiciones }}
          </td>
        </tr>
        <tr>
          <td
            colspan="2"
            class="cot-doc__garantia"
          >
            *** {{ TIENDA.garantia }}
          </td>
        </tr>
      </tbody>
    </table>

    <!-- ── Despedida ── -->
    <footer class="cot-doc__pie">
      <div>
        <p>Sin otro particular y a la espera de concretar su orden, quedamos de ustedes muy atentamente.</p>
        <p class="cot-doc__firma">
          {{ TIENDA.nombre }}
        </p>
      </div>
      <div
        class="cot-doc__sello"
        aria-hidden="true"
      >
        Precios sujetos a descuentos
      </div>
    </footer>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import logo from '@/assets/logo.png'
import { TIENDA } from '@/config/tienda'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'
import { desgloseDe, ETIQUETA_IGV } from '@/utils/igv'
import { fechaCorta } from './constantes'

const props = defineProps({
  // CotizacionResource con ítems.
  cotizacion: {
    type: Object,
    required: true
  }
})

/** "Tinta 544 — Botella 65 ml Negro": producto, presentación y color. */
function descripcion (item) {
  const v = item.variante
  const presentacion = [v.presentacion, v.color?.nombre].filter(Boolean).join(' ')
  return [v.producto?.nombre, presentacion].filter(Boolean).join(' — ')
}

/** "7 días (hasta el 10/04/2026), sujeto a variación de precios sin previo aviso." */
const validez = computed(() => {
  const { fecha, valida_hasta: hasta } = props.cotizacion
  if (!hasta) return 'Sujeto a variación de precios sin previo aviso.'
  const dias = fecha
    ? Math.max(1, Math.round((new Date(`${hasta}T00:00:00`) - new Date(`${fecha.slice(0, 10)}T00:00:00`)) / 86400000))
    : null
  const plazo = dias ? `${dias} ${dias === 1 ? 'día' : 'días'} (hasta el ${fechaCorta(hasta)})` : `Hasta el ${fechaCorta(hasta)}`
  return `${plazo}, sujeto a variación de precios sin previo aviso.`
})
</script>

<style lang="scss" scoped>
// Azul de la marca (el de relleno, AA con texto blanco) y su tinte.
$azul: #1D4ED8;
$azul-tinte: #DBEAFE;
$tinta: #111827;
$gris: #4B5563;

.cot-doc {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 10pt;
  line-height: 1.4;
  color: $tinta;
  background: #FFFFFF;
  print-color-adjust: exact;
  -webkit-print-color-adjust: exact;
}

// ── Membrete ──
.cot-doc__membrete {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 18px;
}

.cot-doc__logo {
  display: block;
  height: 64px;
}

.cot-doc__lema {
  margin: 0;
  font-size: 8.5pt;
  font-weight: 700;
  letter-spacing: 0.3px;
  line-height: 1.5;
  text-align: center;
  text-transform: uppercase;
  color: $gris;
}

.cot-doc__centro {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.cot-doc__horario {
  margin: 0;
  padding: 3px 10px;
  border-radius: 4px;
  background: rgba($azul, 0.08);
  font-size: 8.5pt;
  font-weight: 700;
  text-align: center;
  text-transform: uppercase;
  color: $azul;
}

.cot-doc__caja {
  min-width: 170px;
  padding: 8px 14px;
  border: 1.5px solid $azul;
  border-radius: 8px;
  text-align: center;
}

.cot-doc__ruc {
  font-size: 10.5pt;
}

.cot-doc__titulo {
  margin: 2px 0;
  padding: 2px 0;
  background: $azul;
  font-size: 15pt;
  font-weight: 800;
  letter-spacing: 2px;
  color: #FFFFFF;
}

.cot-doc__codigo {
  font-family: monospace;
  font-size: 11pt;
  font-weight: 700;
}

.cot-doc__contacto {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  margin: 12px 0 14px;
  padding: 6px 2px;
  border-top: 3px solid $azul;
  border-bottom: 1px solid $azul;
  font-size: 9.5pt;
  font-weight: 700;
  text-transform: uppercase;
}

// ── Cliente ──
.cot-doc__bloque {
  border: 1px solid $azul;
  border-radius: 8px;
  overflow: hidden;
}

.cot-doc__banda {
  margin: 0;
  padding: 4px 10px;
  background: $azul-tinte;
  font-size: 9pt;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: $azul;
}

.cot-doc__cliente {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 24px;
  padding: 8px 10px;

  dl {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 3px 12px;
    margin: 0;
  }

  dt {
    font-size: 8.5pt;
    font-weight: 700;
    text-transform: uppercase;
    color: $gris;
  }

  dd {
    margin: 0;
  }
}

.cot-doc__fuerte {
  font-weight: 700;
}

// ── Detalle ──
.cot-doc__tabla {
  width: 100%;
  margin-top: 14px;
  border-collapse: separate;
  border-spacing: 0;
  border: 1px solid $azul;
  border-radius: 8px;
  overflow: hidden;

  th {
    padding: 7px 10px;
    background: $azul;
    font-size: 9pt;
    font-weight: 700;
    text-align: left;
    text-transform: uppercase;
    color: #FFFFFF;
  }

  td {
    padding: 6px 10px;
    border-top: 1px solid #E5E7EB;
    vertical-align: top;
  }

  tbody tr:nth-child(even) td {
    background: #F5F8FF;
  }

  tr {
    break-inside: avoid;
  }
}

.cot-doc__cant {
  width: 64px;
  text-align: center !important;
  white-space: nowrap;
}

.cot-doc__und {
  font-size: 8pt;
  color: $gris;
}

.cot-doc__num {
  width: 100px;
  text-align: right !important;
  white-space: nowrap;
}

.cot-doc__producto {
  font-weight: 700;
  text-transform: uppercase;
}

.cot-doc__sku {
  font-family: monospace;
  font-size: 8pt;
  color: $gris;
}

// ── Totales ──
.cot-doc__resumen {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  margin-top: 10px;
}

.cot-doc__letras {
  flex: 1;
  margin: 0;
  padding: 8px 10px;
  border: 1px solid $azul;
  border-radius: 8px;
  font-size: 9pt;
  font-weight: 700;
}

.cot-doc__totales {
  display: grid;
  grid-template-columns: auto 110px;
  gap: 3px 14px;
  min-width: 240px;
  margin: 0;

  dt {
    color: $gris;
  }

  dd {
    margin: 0;
    text-align: right;
  }
}

.cot-doc__total {
  margin-top: 4px;
  padding: 5px 8px !important;
  background: $azul;
  font-size: 12pt;
  font-weight: 800;
  text-transform: uppercase;
  color: #FFFFFF !important;
}

dt.cot-doc__total {
  border-radius: 6px 0 0 6px;
}

dd.cot-doc__total {
  border-radius: 0 6px 6px 0;
}

.cot-doc__igv {
  margin: 4px 0 0;
  font-size: 8.5pt;
  text-align: right;
  color: $gris;
}

// ── Condiciones ──
.cot-doc__condiciones {
  width: 100%;
  margin-top: 14px;
  border-collapse: collapse;
  font-size: 9pt;

  th,
  td {
    padding: 4px 10px;
    border: 1px solid $azul;
    text-align: left;
    vertical-align: top;
  }

  th {
    width: 150px;
    background: $azul-tinte;
    font-weight: 700;
    text-transform: uppercase;
    color: $azul;
  }

  td {
    font-weight: 700;
  }
}

.cot-doc__multilinea {
  white-space: pre-line;
}

.cot-doc__garantia {
  font-size: 8.5pt;
  font-weight: 400 !important;
  color: $gris;
}

// ── Despedida ──
.cot-doc__pie {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-top: 18px;
  padding: 10px 14px;
  border: 1px solid $azul;
  border-radius: 8px;
  font-size: 9.5pt;

  p {
    margin: 0;
  }
}

.cot-doc__firma {
  margin-top: 4px !important;
  font-weight: 800;
  text-align: center;
}

.cot-doc__sello {
  flex-shrink: 0;
  width: 92px;
  padding: 8px 6px;
  border: 2px dashed $azul;
  border-radius: 999px;
  font-size: 7.5pt;
  font-weight: 800;
  line-height: 1.2;
  text-align: center;
  text-transform: uppercase;
  color: $azul;
  transform: rotate(-8deg);
}
</style>
