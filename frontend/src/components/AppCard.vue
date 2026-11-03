<template>
  <q-card
    flat
    :class="['app-card', `app-card--${props.variant}`]"
  >
    <slot />
  </q-card>
</template>

<script>
// Igual que en AppButton y AppChip: defineProps() se hoistea al scope de
// módulo, así que la lista no puede vivir dentro del setup.
export const VARIANTS = ['surface', 'highlight']
</script>

<script setup>
const props = defineProps({
  variant: {
    type: String,
    default: 'surface',
    validator: (value) => VARIANTS.includes(value)
  }
})
</script>

<style lang="scss" scoped>
// A propósito no define padding: el padding es composición, no identidad de
// superficie, y varía según el uso. Se compone desde afuera con q-pa-* o
// con q-card-section.
.app-card {
  border-radius: 12px;
}

.app-card--surface {
  background: var(--app-surface);
  border: 1px solid var(--app-border-subtle);
  box-shadow: var(--app-shadow-card);
}

// Sin sombra, y no es un olvido: una superficie tintada ya se separa del
// fondo por color. Sumarle elevación la ensucia y le compite al dato.
.app-card--highlight {
  background: var(--app-brand-soft);
  border: 1px solid rgba($primary, 0.18);
}
</style>
