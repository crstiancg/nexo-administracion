<template>
  <span :class="['app-chip', `app-chip--${props.status}`]">
    <q-icon
      :name="icon"
      class="app-chip__icon"
    />
    {{ props.label }}
  </span>
</template>

<script>
// Mismo motivo que en AppButton: defineProps() es un macro que el compilador
// hoistea al scope de módulo, así que el mapa tiene que vivir acá afuera.
export const STATUS_ICONS = {
  positive: 'check',
  warning: 'schedule',
  info: 'sync',
  negative: 'close'
}
</script>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    required: true,
    validator: (value) => value in STATUS_ICONS
  },

  // Obligatorio a propósito: el estado nunca puede comunicarse sólo por color.
  label: {
    type: String,
    required: true
  }
})

// El ícono lo decide el componente, no el llamador: así no se puede olvidar.
const icon = computed(() => STATUS_ICONS[props.status])
</script>

<style lang="scss" scoped>
.app-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 26px;
  padding: 0 10px 0 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.app-chip__icon {
  font-size: 15px;
}

// Ni el tinte ni el texto se hardcodean: leen las custom properties que
// publica app.scss, que son las que cambian con el tema. En claro el texto
// se oscurece; en oscuro se aclara. El componente no sabe en qué tema está,
// y eso es justamente lo que lo hace funcionar en los dos.
@each $name, $color in $statuses {
  .app-chip--#{$name} {
    background: var(--app-tint-#{$name});
    color: var(--app-ink-#{$name});
  }
}

// El único con contorno. No es decoración: es el refuerzo que separa el rojo
// de error del rojo de marca, que nunca lleva borde porque siempre va relleno.
.app-chip--negative {
  border: 1px solid rgba($negative, 0.22);
}
</style>
