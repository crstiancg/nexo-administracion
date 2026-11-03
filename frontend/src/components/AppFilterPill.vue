<template>
  <q-btn-dropdown
    flat
    no-caps
    unelevated
    content-class="app-filter-pill__menu"
    class="app-filter-pill"
  >
    <template #label>
      <span class="app-filter-pill__label">{{ label }}</span>
      <span class="app-filter-pill__value">{{ valueLabel }}</span>
    </template>

    <q-list>
      <q-item
        v-for="opt in options"
        :key="opt.label"
        v-close-popup
        clickable
        @click="model = opt.value"
      >
        <q-item-section>{{ opt.label }}</q-item-section>
      </q-item>
    </q-list>
  </q-btn-dropdown>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: {
    type: String,
    required: true
  },

  // Por convención la primera opción es el "sin filtro" (ej. { label:
  // 'Todos', value: null }). El componente no le da trato especial: sólo la
  // usa de fallback para el label cuando el valor actual no matchea ninguna.
  options: {
    type: Array,
    required: true
  }
})

const model = defineModel()

const valueLabel = computed(
  () => props.options.find((opt) => opt.value === model.value)?.label ?? props.options[0]?.label ?? ''
)
</script>

<style lang="scss" scoped>
.app-filter-pill {
  height: 40px;
  padding: 0 8px 0 13px;
  border-radius: 9px;
  border: 1px solid var(--app-border-control);
  font-size: 13.5px;

  :deep(.q-btn__content) {
    justify-content: flex-start;
    gap: 7px;
  }
}

.app-filter-pill__label {
  color: var(--app-ink-2);
}

.app-filter-pill__value {
  font-weight: 600;
  color: var(--app-ink);
}

@media (max-width: 599px) {
  .app-filter-pill {
    width: 100%;
  }
}
</style>
