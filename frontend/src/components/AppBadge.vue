<template>
  <q-badge
    :class="[
      'app-badge',
      `app-badge--${props.variant}`,
      { 'app-badge--dot': props.dot }
    ]"
  >
    <slot v-if="!props.dot" />

    <!-- El punto no dice nada por sí solo: para un lector de pantalla es
         invisible. El texto va oculto visualmente, pero existe. -->
    <span
      v-else
      class="app-badge__sr"
    >{{ props.srLabel }}</span>
  </q-badge>
</template>

<script>
// Igual que en los otros: defineProps() se hoistea al scope de módulo.
export const VARIANTS = ['brand', 'soft']
</script>

<script setup>
// Sin prop `color` de Quasar, por la misma razón que AppButton: sus clases
// bg-* aplican con !important y habría que pisarlas desde afuera.
const props = defineProps({
  variant: {
    type: String,
    default: 'brand',
    validator: (value) => VARIANTS.includes(value)
  },

  // Indicador sin cifra: "hay algo nuevo", sin decir cuánto.
  dot: {
    type: Boolean,
    default: false
  },

  // Obligatorio cuando dot está activo: es la única forma de que el
  // indicador exista para quien no ve el color.
  srLabel: {
    type: String,
    default: ''
  }
})

if (import.meta.env.DEV && props.dot && !props.srLabel) {
  console.warn('[AppBadge] `dot` sin `srLabel`: el indicador es invisible para lectores de pantalla.')
}
</script>

<style lang="scss" scoped>
.app-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 20px;
  padding: 0 7px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
}

// #FFFFFF literal, no var(--app-surface): es blanco sobre el relleno de
// marca, que no cambia con el tema.
.app-badge--brand {
  background: $primary;
  color: #FFFFFF;
}

.app-badge--soft {
  background: var(--app-brand-soft);
  color: var(--app-brand-soft-ink);
}

.app-badge--dot {
  min-width: 0;
  width: 8px;
  height: 8px;
  padding: 0;
}

// Oculto a la vista, presente para el lector de pantalla. No usamos
// display:none ni visibility:hidden porque eso lo saca del árbol accesible.
.app-badge__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

// El anillo aparece solo cuando el badge flota sobre otra cosa —Quasar pone
// .q-badge--floating al pasar `floating`. No es adorno: sin el corte de
// superficie, el badge se funde con el ícono que tiene debajo.
// Este sí es la superficie del tema: el anillo tiene que ser del color de lo
// que está DETRÁS para recortar el badge del ícono. Cambia con el tema.
.app-badge.q-badge--floating {
  border: 1.5px solid var(--app-surface);
}
</style>
