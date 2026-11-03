<template>
  <q-dialog
    v-model="model"
    :persistent="persistent"
  >
    <AppCard :class="['app-dialog', `app-dialog--${size}`]">
      <div class="app-dialog__header">
        <h2 class="app-dialog__title">{{ title }}</h2>

        <q-btn
          flat
          dense
          round
          icon="close"
          size="sm"
          color="grey-7"
          aria-label="Cerrar"
          @click="model = false"
        />
      </div>

      <q-separator class="app-dialog__sep" />

      <div class="app-dialog__body">
        <slot />
      </div>

      <template v-if="$slots.actions">
        <q-separator class="app-dialog__sep" />

        <div class="app-dialog__actions">
          <slot name="actions" />
        </div>
      </template>
    </AppCard>
  </q-dialog>
</template>

<script setup>
import AppCard from './AppCard.vue'

defineProps({
  title: {
    type: String,
    required: true
  },

  // Como en QDialog: true impide cerrar con click afuera o Esc. Para
  // formularios donde perder lo tipeado sin querer sale caro.
  persistent: {
    type: Boolean,
    default: false
  },

  // md: confirmaciones y forms de pocos campos. lg: forms con varias
  // secciones (ej. usuario: datos + roles + permisos).
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['md', 'lg'].includes(value)
  }
})

const model = defineModel()
</script>

<style lang="scss" scoped>
.app-dialog {
  width: 480px;
  max-width: 90vw;
}

// QDialog le pone max-width 560px a su contenido; sin pisarlo, `lg` quedaría
// recortado igual que `md`.
.app-dialog--lg {
  width: 880px;
  max-width: 94vw !important;
}

.app-dialog__header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 18px 12px 18px 24px;
}

.app-dialog__title {
  margin: 0;
  margin-right: auto;
  font-size: 17px;
  font-weight: 700;
  letter-spacing: -0.3px;
  line-height: 1.1;
  color: var(--app-ink);
}

.app-dialog__sep {
  background: var(--app-border-subtle);
}

.app-dialog__body {
  padding: 20px 24px;
}

.app-dialog__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
}

@media (max-width: 599px) {
  .app-dialog {
    width: 100%;
  }
}
</style>
