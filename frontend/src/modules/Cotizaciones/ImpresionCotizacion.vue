<template>
  <!-- En <body> y oculto en pantalla: al imprimir es lo único que sale
       (reglas .documento-impresion en css/app.scss, hoja A4). -->
  <Teleport to="body">
    <div
      v-if="cotizacion"
      class="documento-impresion"
    >
      <CotizacionDocumento :cotizacion="cotizacion" />
    </div>
  </Teleport>
</template>

<script setup>
import { nextTick, ref } from 'vue'
import CotizacionDocumento from './CotizacionDocumento.vue'

/**
 * Imprime la cotización (o la guarda en PDF) con el diálogo del navegador,
 * igual que el ticket.
 */
const cotizacion = ref(null)

async function imprimir (datos) {
  cotizacion.value = datos
  await nextTick()
  window.print()
}

defineExpose({ imprimir })
</script>
