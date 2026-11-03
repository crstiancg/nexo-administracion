<template>
  <!-- Los productos que se van a cobrar, completos y legibles (en el
       carrito a veces no entran). Cliente, pago y totales: CarritoPanel. -->
  <div class="resumen">
    <div class="resumen__tablaWrap">
      <table class="resumen__tabla">
        <thead>
          <tr>
            <th>Producto</th>
            <th class="text-right">
              Cant.
            </th>
            <th class="text-right">
              P. unit.
            </th>
            <th class="text-right">
              Importe
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in pos.items"
            :key="item.variante_id"
          >
            <!-- Nombre y presentación en una fila: una línea por producto. -->
            <td class="resumen__celdaProducto">
              <span class="resumen__producto">{{ item.nombre }}</span>
              <span class="resumen__meta">
                {{ item.presentacion }}<template v-if="item.color">
                  · {{ item.color.nombre }}
                </template>
              </span>
            </td>
            <td class="text-right text-mono resumen__cant">
              {{ item.cantidad }}
            </td>
            <td class="text-right text-mono">
              {{ formatearPrecio(item.precio_unitario) }}
            </td>
            <td class="text-right text-mono resumen__importe">
              {{ formatearPrecio(item.cantidad * Number(item.precio_unitario || 0)) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="resumen__aviso">
      Al confirmar se registra la venta y se descuenta el stock de {{ sede }}.
      <strong>Enter</strong> o <strong>F9</strong> confirma · <strong>Esc</strong> vuelve.
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePosStore } from '@/stores/pos-store'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'

/**
 * Los productos de la venta en el diálogo de cobro del punto de venta. Lo
 * muestra CarritoPanel, que pone al lado el cliente, el pago y confirmar.
 */
const pos = usePosStore()
const userStore = useUserStore()

const sede = computed(() => userStore.sede?.nombre ?? 'tu sede')
</script>

<style lang="scss" scoped>
.resumen {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.resumen__meta {
  font-size: 12px;
  color: var(--app-ink-2);
}

.resumen__tablaWrap {
  overflow-y: auto;
  max-height: 52vh;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
}

.resumen__tabla {
  width: 100%;
  border-collapse: collapse;

  th {
    position: sticky;
    top: 0;
    padding: 6px 10px;
    background: var(--app-page);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    color: var(--app-ink-2);
  }

  td {
    padding: 5px 10px;
    border-top: 1px solid var(--app-border-subtle);
    font-size: 13px;
    white-space: nowrap;
    color: var(--app-ink);
  }

  .text-right {
    text-align: right;
  }
}

.resumen__celdaProducto {
  overflow: hidden;
  max-width: 0;
  width: 100%;
  text-overflow: ellipsis;
}

.resumen__producto {
  margin-right: 6px;
  font-weight: 600;
}

.resumen__cant {
  font-size: 14px !important;
  font-weight: 700;
}

.resumen__importe {
  font-weight: 700;
}

.resumen__aviso {
  margin: 0;
  font-size: 12.5px;
  color: var(--app-ink-2);
}
</style>
