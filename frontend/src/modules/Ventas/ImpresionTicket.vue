<template>
  <!-- En <body> y oculto en pantalla: al imprimir es lo único que sale
       (reglas .ticket-impresion en css/app.scss). -->
  <Teleport to="body">
    <div
      v-if="pedido"
      class="ticket-impresion"
    >
      <TicketVenta :pedido="pedido" />
    </div>
  </Teleport>
</template>

<script setup>
import { nextTick, ref } from 'vue'
import TicketVenta from './TicketVenta.vue'

/**
 * Imprime el ticket con el diálogo del navegador: la ticketera se instala en
 * Windows como una impresora más (con su driver) y se elige ahí. Sin
 * servicios extra en la PC.
 */
const pedido = ref(null)

async function imprimir (datos) {
  pedido.value = datos
  // Que el ticket esté en el DOM antes de abrir el diálogo de impresión.
  await nextTick()
  window.print()
}

defineExpose({ imprimir })
</script>
