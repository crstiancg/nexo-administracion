<template>
  <q-btn
    no-caps
    unelevated
    :class="['app-btn', `app-btn--${props.variant}`]"
  >
    <slot />
  </q-btn>
</template>

<script>
// Va en un <script> normal y no en <script setup>: defineProps() es un macro
// que el compilador hoistea al scope de módulo, así que no puede leer nada
// declarado dentro del setup.
export const VARIANTS = ['primary', 'secondary', 'tertiary', 'destructive']
</script>

<script setup>
// Sin prop `color` de Quasar a propósito: sus clases bg-* aplican con
// !important, así que delegarle el primario obligaba a pisarlo desde
// app.scss. Las cuatro variantes se estilizan igual, acá y sin !important.
const props = defineProps({
  variant: {
    type: String,
    default: 'secondary',
    validator: (value) => VARIANTS.includes(value)
  }
})
</script>

<style lang="scss" scoped>
.app-btn {
  height: 40px;
  padding: 0 16px;
  border-radius: 9px;
  font-size: 13.5px;
  font-weight: 600;
  letter-spacing: 0;

  // Quasar tinta el hover con una capa .q-focus-helper sobre currentColor,
  // que desviaría el color exacto de cada variante.
  &:hover :deep(.q-focus-helper) {
    opacity: 0 !important;
  }

  // La capa anterior también dibujaba el foco de teclado, así que lo
  // reponemos explícitamente para no perder accesibilidad.
  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
}

// OJO: el texto es #FFFFFF literal, NO var(--app-surface). Este blanco no es
// "la superficie del tema", es blanco sobre el relleno de marca —y el relleno
// de marca no cambia con el tema. Con la var, en oscuro quedaría texto gris
// oscuro sobre rojo.
.app-btn--primary {
  background: $primary;
  color: #FFFFFF;
  box-shadow: 0 1px 2px rgba($primary-hover, 0.28);

  &:hover {
    background: $primary-hover;
  }
}

.app-btn--secondary {
  background: var(--app-surface);
  color: var(--app-ink);
  border: 1px solid var(--app-border-control);

  &:hover {
    background: var(--app-page);
    border-color: var(--app-border-control-hover);
  }
}

// $primary-hover crudo sobre superficie oscura da 2.56:1: ilegible. El token
// se aclara solo cuando cambia el tema.
.app-btn--tertiary {
  color: var(--app-brand-soft-ink);

  &:hover {
    background: var(--app-brand-soft);
  }
}

// Mismo caso: $negative crudo sobre oscuro da 2.29:1.
.app-btn--destructive {
  background: var(--app-surface);
  color: var(--app-ink-negative);
  border: 1px solid var(--app-negative-border);

  &:hover {
    background: var(--app-negative-soft);
    border-color: var(--app-ink-negative);
  }
}

// Deshabilitado siempre neutro: pierde la marca, no la atenúa. Quasar solo
// bajaría la opacidad, lo que dejaría un rojo pálido que sigue leyéndose
// como acción disponible.
.app-btn.disabled {
  opacity: 1 !important;
  background: var(--app-disabled-bg);
  color: var(--app-disabled-ink);
  border-color: transparent;
  box-shadow: none;
}

.app-btn--tertiary.disabled {
  background: transparent;
}

// El diseño pide 44px de alto táctil en mobile.
@media (max-width: 599px) {
  .app-btn {
    height: 44px;
  }
}
</style>
