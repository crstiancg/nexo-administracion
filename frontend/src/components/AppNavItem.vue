<template>
  <router-link
    :to="props.to"
    class="app-nav-item"
    :active-class="activeClass"
    exact-active-class="app-nav-item--active"
  >
    <q-icon
      :name="props.icon"
      class="app-nav-item__icon"
    />

    <span class="app-nav-item__label">{{ props.label }}</span>

    <span
      v-if="$slots.badge"
      class="app-nav-item__badge"
    >
      <slot name="badge" />
    </span>
  </router-link>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  to: {
    type: [String, Object],
    required: true
  },

  label: {
    type: String,
    required: true
  },

  icon: {
    type: String,
    required: true
  },

  // Para la raíz ("/"), que si no matchea con todo lo de abajo y queda
  // siempre activa.
  exact: {
    type: Boolean,
    default: false
  }
})

// El estado activo NO es una prop: lo decide el router, que es la única
// fuente de verdad de dónde estás. Pasarlo a mano se desincroniza.
// Con `exact` sólo pinta el match exacto; si no, también pinta las rutas
// hijas, para que /pedidos/123 siga marcando "Pedidos".
const activeClass = computed(() => (props.exact ? '' : 'app-nav-item--active'))
</script>

<style lang="scss" scoped>
.app-nav-item {
  display: flex;
  align-items: center;
  gap: 11px;
  height: 40px;
  padding: 0 11px;
  border-radius: 9px;
  font-size: 14px;
  font-weight: 500;
  color: var(--app-ink-2);
  text-decoration: none;

  &:hover {
    background: var(--app-page);
  }
}

.app-nav-item__icon {
  font-size: 19px;
  flex-shrink: 0;
}

.app-nav-item__label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-nav-item__badge {
  margin-left: auto;
  display: flex;
  align-items: center;
}

.app-nav-item--active {
  background: var(--app-brand-soft);
  color: var(--app-brand-soft-ink);
  font-weight: 600;

  // El hover no cambia nada cuando ya está activo: el ítem no es un
  // destino, ya estás ahí.
  &:hover {
    background: var(--app-brand-soft);
  }
}

@media (max-width: 599px) {
  .app-nav-item {
    height: 44px;
  }
}
</style>
