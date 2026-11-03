<template>
  <span :class="['app-logo', { 'app-logo--placa': conPlaca }]">
    <img
      :src="src"
      :alt="alt"
      class="app-logo__img"
      :style="{ height: `${alto}px` }"
    >
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { useQuasar } from 'quasar'
import logo from '@/assets/logo.png'
import logoOscuro from '@/assets/logo-oscuro.png'
import { TIENDA } from '@/config/tienda'

/**
 * El logo de la empresa. Por defecto el de Nexo Technology, con su versión de
 * letras blancas para fondo oscuro (tema oscuro, foto del acceso). Si se subió
 * otro en Configuración, se usa ése; como no se sabe de qué color es, sobre
 * fondo oscuro va en una placa blanca.
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

  // Otro logo (p. ej. el que trae la página pública de seguimiento).
  url: {
    type: String,
    default: null
  },

  alt: {
    type: String,
    default: 'Logo de la empresa'
  }
})

const $q = useQuasar()
const fondoOscuro = computed(() => props.oscuro || $q.dark.isActive)
const propio = computed(() => props.url || TIENDA.logo_url)

const src = computed(() => propio.value || (fondoOscuro.value ? logoOscuro : logo))
const conPlaca = computed(() => Boolean(propio.value) && fondoOscuro.value)
</script>

<style lang="scss" scoped>
.app-logo {
  display: inline-flex;
  align-items: center;
  border-radius: 8px;
}

.app-logo--placa {
  padding: 4px 8px;
  background: #FFFFFF;
}

.app-logo__img {
  display: block;
  width: auto;
  max-width: 220px;
  object-fit: contain;
}
</style>
