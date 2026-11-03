<template>
  <!-- En <body> y oculto en pantalla: al imprimir es lo único que sale
       (reglas .documento-impresion en css/app.scss, hoja A4). -->
  <Teleport to="body">
    <div
      v-if="orden"
      class="documento-impresion"
    >
      <OrdenCompraDocumento :orden="orden" />
    </div>
  </Teleport>
</template>

<script setup>
import { nextTick, ref } from 'vue'
import OrdenCompraDocumento from './OrdenCompraDocumento.vue'

/**
 * Imprime la orden (o la guarda en PDF para mandarla por correo o WhatsApp)
 * con el diálogo del navegador, igual que la cotización.
 */
const orden = ref(null)

async function imprimir (datos) {
  orden.value = datos
  await nextTick()
  // Al cerrar el diálogo se saca del DOM: no sale junto a otro impreso.
  window.addEventListener('afterprint', () => { orden.value = null }, { once: true })
  window.print()
}

defineExpose({ imprimir })
</script>
