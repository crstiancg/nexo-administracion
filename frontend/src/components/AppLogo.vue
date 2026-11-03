<template>
  <img
    :src="usarOscuro ? logoOscuro : logo"
    :alt="alt"
    class="app-logo"
    :style="{ height: `${alto}px` }"
  >
</template>

<script setup>
import { computed } from 'vue'
import { useQuasar } from 'quasar'
import logo from '@/assets/logo.png'
import logoOscuro from '@/assets/logo-oscuro.png'

/**
 * El logo de Nexo Technology. Las letras del original son negras: sobre fondo
 * oscuro (tema oscuro, foto del acceso) se usa la versión con letras blancas
 * (logo-oscuro.png, el mismo anillo azul y rojo).
 */
const props = defineProps({
  // En px; el ancho sale de la proporción del logo.
  alto: {
    type: Number,
    default: 36
  },

  // Siempre la versión para fondo oscuro (p. ej. sobre una foto).
  oscuro: {
    type: Boolean,
    default: false
  },

  alt: {
    type: String,
    default: 'Nexo Technology'
  }
})

const $q = useQuasar()
const usarOscuro = computed(() => props.oscuro || $q.dark.isActive)
</script>

<style lang="scss" scoped>
.app-logo {
  display: block;
  width: auto;
}
</style>
