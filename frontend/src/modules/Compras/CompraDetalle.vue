<template>
  <div
    v-if="compra"
    class="compra-detalle"
  >
    <header class="compra-detalle__cabecera">
      <div>
        <div class="compra-detalle__codigo text-mono">
          {{ compra.codigo }}
        </div>
        <div class="compra-detalle__meta">
          {{ compra.tipo_documento_label }} {{ compra.numero_documento }} · {{ fechaCorta(compra.fecha) }}
          · {{ compra.sede?.nombre }}
          <template v-if="compra.usuario">
            · {{ compra.usuario.name }}
          </template>
        </div>
      </div>
      <AppChip
        :status="ESTADOS[compra.estado].status"
        :label="ESTADOS[compra.estado].label"
      />
    </header>

    <section class="compra-detalle__proveedor">
      <q-icon
        name="local_shipping"
        size="18px"
      />
      <div>
        <div class="compra-detalle__fuerte">
          {{ compra.proveedor?.razon_social }}
        </div>
        <div class="compra-detalle__meta">
          RUC {{ compra.proveedor?.ruc }}<template v-if="compra.proveedor?.telefono">
            · {{ compra.proveedor.telefono }}
          </template>
        </div>
      </div>
    </section>

    <table class="compra-detalle__tabla">
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
            Costo
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
          v-for="item in compra.items"
          :key="item.id"
        >
          <td>
            <div class="compra-detalle__fuerte">
              {{ item.variante.producto?.nombre }}
            </div>
            <div class="compra-detalle__meta">
              {{ item.variante.presentacion }}<template v-if="item.variante.color">
                · {{ item.variante.color.nombre }}
              </template> ·
              <span class="text-mono">{{ item.variante.sku }}</span>
            </div>
            <div
              v-if="item.lote"
              class="compra-detalle__meta"
            >
              Lote {{ item.lote }}<template v-if="item.vence_at">
                · vence {{ fechaCorta(item.vence_at) }}
              </template>
            </div>
          </td>
          <td class="text-right text-mono">
            {{ formatearCantidad(item.cantidad) }} {{ item.variante.unidad }}
          </td>
          <td class="text-right text-mono">
            {{ formatearPrecio(item.costo_unitario) }}
          </td>
          <td class="text-right text-mono">
            {{ formatearPrecio(item.subtotal) }}
          </td>
        </tr>
      </tbody>
    </table>

    <dl class="compra-detalle__totales">
      <dt>Op. gravada</dt>
      <dd class="text-mono">
        {{ formatearPrecio(desgloseDe(compra).opGravada) }}
      </dd>
      <dt>{{ ETIQUETA_IGV }}</dt>
      <dd class="text-mono">
        {{ formatearPrecio(desgloseDe(compra).igv) }}
      </dd>
      <dt class="compra-detalle__total">
        Total
      </dt>
      <dd class="compra-detalle__total text-mono">
        {{ formatearPrecio(compra.total) }}
      </dd>
    </dl>

    <p
      v-if="compra.observacion"
      class="compra-detalle__nota"
    >
      {{ compra.observacion }}
    </p>

    <p
      v-if="compra.estado === 'anulada'"
      class="compra-detalle__nota compra-detalle__nota--anulada"
    >
      Anulada {{ formatearFecha(compra.anulada_at) }}<template v-if="compra.anulada_por">
        por {{ compra.anulada_por.name }}
      </template>: {{ compra.motivo_anulacion }}. La mercadería salió del inventario.
    </p>
  </div>

  <div
    v-else
    class="compra-detalle__cargando"
  >
    <q-spinner size="24px" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import AppChip from '@/components/AppChip.vue'
import CompraService from '@/services/CompraService'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'
import { desgloseDe, ETIQUETA_IGV } from '@/utils/igv'
import { ESTADOS, fechaCorta } from './constantes'

const props = defineProps({
  id: {
    type: Number,
    required: true
  }
})

const compra = ref(null)

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })
function formatearFecha (iso) {
  return iso ? formatoFecha.format(new Date(iso)) : ''
}

onMounted(async () => {
  compra.value = await CompraService.get(props.id)
})

// Las acciones las dispara la lista (dueña de los diálogos); acá se refresca.
function actualizar (nueva) {
  compra.value = nueva
}

defineExpose({ compra, actualizar })
</script>

<style lang="scss" scoped>
.compra-detalle {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.compra-detalle__cabecera {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.compra-detalle__codigo {
  font-size: 18px;
  font-weight: 700;
  color: var(--app-ink);
}

.compra-detalle__meta {
  font-size: 12px;
  color: var(--app-ink-2);
}

.compra-detalle__proveedor {
  display: flex;
  gap: 10px;
  padding: 12px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
  color: var(--app-ink-2);
}

.compra-detalle__fuerte {
  font-weight: 600;
  color: var(--app-ink);
}

.compra-detalle__tabla {
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

.compra-detalle__totales {
  display: grid;
  grid-template-columns: auto 140px;
  gap: 6px 16px;
  justify-content: end;
  margin: 0;

  dd {
    margin: 0;
    text-align: right;
  }
}

.compra-detalle__total {
  font-size: 16px;
  font-weight: 700;
  color: var(--app-ink);
}

.compra-detalle__nota {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--app-border-subtle);
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink);
  white-space: pre-line;

  &--anulada {
    background: rgba(220, 38, 38, 0.08);
    color: var(--q-negative);
  }
}

.compra-detalle__cargando {
  display: flex;
  justify-content: center;
  padding: 32px;
}
</style>
