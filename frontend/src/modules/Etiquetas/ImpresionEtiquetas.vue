<template>
  <!-- En <body> y oculto en pantalla, como el ticket: al imprimir es lo único
       que sale (reglas .etiquetas-impresion en css/app.scss). -->
  <Teleport to="body">
    <div
      v-if="hojas.length"
      :class="['etiquetas-impresion', `etiquetas-impresion--${formato.tipo}`]"
    >
      <div
        v-for="(hoja, h) in hojas"
        :key="h"
        class="etiquetas-hoja"
        :style="estiloHoja"
      >
        <div
          v-for="(variante, i) in hoja"
          :key="i"
          class="etiquetas-celda"
        >
          <EtiquetaImpresa
            v-if="variante"
            :variante="variante"
            :formato="formato"
          />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'
import EtiquetaImpresa from './EtiquetaImpresa.vue'
import { A4, paginar } from './formatos'

/**
 * Imprime con el diálogo del navegador, igual que el ticket: la impresora
 * (láser con hojas de stickers o la ticketera) se elige ahí.
 */
const hojas = ref([])
const formato = ref({ tipo: 'a4', columnas: 1, filas: 1, ancho: 0, alto: 0 })

// En A4 la grilla se centra en la hoja: lo que sobra de 297 mm (en 3×8 es
// medio milímetro) se reparte arriba y abajo.
const estiloHoja = computed(() => {
  const f = formato.value
  const grilla = {
    gridTemplateColumns: `repeat(${f.columnas}, ${f.ancho}mm)`,
    gridAutoRows: `${f.alto}mm`
  }
  if (f.tipo !== 'a4') return grilla

  return {
    ...grilla,
    paddingTop: `${(A4.alto - f.filas * f.alto) / 2}mm`,
    paddingLeft: `${(A4.ancho - f.columnas * f.ancho) / 2}mm`
  }
})

/**
 * @param {object[]} etiquetas una variante por etiqueta (ya expandidas)
 * @param {object} formatoElegido uno de FORMATOS
 * @param {number} inicio posición del primer sticker libre (sólo A4)
 */
async function imprimir (etiquetas, formatoElegido, inicio = 1) {
  formato.value = formatoElegido
  hojas.value = paginar(etiquetas, formatoElegido, inicio)
  // Que las etiquetas estén en el DOM antes de abrir el diálogo.
  await nextTick()
  window.print()
}

defineExpose({ imprimir })
</script>
