<template>
  <header class="app-page-header">
    <div class="app-page-header__text">
      <h1 class="app-page-header__title">{{ title }}</h1>
      <p
        v-if="subtitle"
        class="app-page-header__sub"
      >
        {{ subtitle }}
      </p>
    </div>

    <div
      v-if="$slots.actions"
      class="app-page-header__actions"
    >
      <slot name="actions" />
    </div>
  </header>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },

  subtitle: {
    type: String,
    default: ''
  }
})
</script>

<style lang="scss" scoped>
.app-page-header {
  display: flex;
  align-items: flex-end;
  gap: 16px;
  flex-wrap: wrap;
}

.app-page-header__title {
  margin: 0 0 5px;
  font-size: 25px;
  font-weight: 700;
  letter-spacing: -0.5px;

  // Sin esto hereda el line-height default de Quasar para <h1> (6rem, pensado
  // para un display heading enorme): el título infla su caja y empuja todo
  // lo de abajo. Vivía repetido —y una vez olvidado— en cada página; ahora
  // sólo puede fallar en un lugar.
  line-height: 1.1;
  color: var(--app-ink);
}

.app-page-header__sub {
  margin: 0;
  font-size: 13.5px;
  color: var(--app-ink-2);
}

.app-page-header__actions {
  margin-left: auto;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
</style>
