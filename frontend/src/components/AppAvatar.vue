<template>
  <span
    class="app-avatar"
    :style="{ '--avatar-tono': tono, '--avatar-size': `${size}px` }"
    aria-hidden="true"
  >
    <q-icon
      v-if="icono"
      :name="icono"
      :size="`${Math.round(size * 0.5)}px`"
    />
    <template v-else>{{ iniciales }}</template>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { initialsOf } from '@/stores/user-store'

/**
 * Círculo con las iniciales del nombre. El color sale del nombre (siempre el
 * mismo para la misma persona), así se reconoce de un vistazo en las listas.
 * Decorativo: el nombre ya está escrito al lado.
 */
const props = defineProps({
  nombre: {
    type: String,
    default: ''
  },
  // En vez de iniciales (p. ej. 'business' para una empresa).
  icono: {
    type: String,
    default: null
  },
  size: {
    type: Number,
    default: 34
  }
})

const iniciales = computed(() => initialsOf(props.nombre) || '?')

// Tonos de la rueda de color, separados para que no se confundan.
const TONOS = [350, 20, 45, 150, 185, 210, 260, 290]
const tono = computed(() => {
  let hash = 0
  for (const letra of props.nombre ?? '') hash = (hash * 31 + letra.charCodeAt(0)) | 0
  return TONOS[Math.abs(hash) % TONOS.length]
})
</script>

<style lang="scss" scoped>
.app-avatar {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: var(--avatar-size);
  height: var(--avatar-size);
  border-radius: 999px;
  background: hsl(var(--avatar-tono) 70% 50% / 0.16);
  color: hsl(var(--avatar-tono) 60% 36%);
  font-size: calc(var(--avatar-size) * 0.36);
  font-weight: 700;
  letter-spacing: 0.3px;
  line-height: 1;
  user-select: none;
}

// Todo dentro de :global(): con `:global(body.body--dark) .app-avatar` Vue
// deja sólo `body.body--dark` y estas reglas pintarían el <body>.
:global(body.body--dark .app-avatar) {
  background: hsl(var(--avatar-tono) 60% 50% / 0.22);
  color: hsl(var(--avatar-tono) 80% 74%);
}
</style>
