<template>
  <ol :class="['tl', `tl--${orientation}`]">
    <!-- Línea de tiempo (estilo Nuxt UI): un punto con ícono por paso y una
         línea que los une. La línea se pinta de color hasta el paso actual.
         El comentario va adentro: afuera haría dos raíces y la clase de
         quien lo usa no llegaría al <ol>. -->
    <li
      v-for="(item, i) in items"
      :key="item.key ?? i"
      :class="['tl__item', `tl__item--${estadoDe(item)}`]"
      :aria-current="estadoDe(item) === 'actual' ? 'step' : undefined"
    >
      <div class="tl__rail">
        <span :class="['tl__punto', `tl__punto--${item.color ?? 'primary'}`]">
          <q-icon
            :name="item.icon ?? (estadoDe(item) === 'hecho' ? 'check' : 'circle')"
            :size="estadoDe(item) === 'pendiente' && !item.icon ? '8px' : '16px'"
          />
        </span>
        <span
          v-if="i < items.length - 1"
          :class="['tl__linea', { 'tl__linea--activa': lineaActiva(i) }]"
        />
      </div>

      <div class="tl__cuerpo">
        <div
          v-if="item.fecha"
          class="tl__fecha"
        >
          {{ item.fecha }}
        </div>
        <div class="tl__titulo">
          <span>{{ item.titulo }}</span>
          <span
            v-if="item.extra"
            class="tl__extra text-mono"
          >{{ item.extra }}</span>
        </div>
        <p
          v-if="item.descripcion"
          class="tl__desc"
        >
          {{ item.descripcion }}
        </p>
        <slot
          name="item"
          :item="item"
          :index="i"
        />
      </div>
    </li>
  </ol>
</template>

<script setup>
const props = defineProps({
  /**
   * [{ titulo, fecha?, descripcion?, extra?, icon?, key?,
   *    color?: 'primary' | 'positive' | 'warning' | 'negative' | 'info' | 'neutral',
   *    estado?: 'hecho' | 'actual' | 'pendiente' }]
   */
  items: {
    type: Array,
    required: true
  },
  orientation: {
    type: String,
    default: 'vertical',
    validator: (v) => ['vertical', 'horizontal'].includes(v)
  }
})

function estadoDe (item) {
  return item.estado ?? 'hecho'
}

// El tramo entre dos pasos se pinta si ya se llegó al siguiente.
function lineaActiva (i) {
  return estadoDe(props.items[i + 1]) !== 'pendiente'
}
</script>

<style lang="scss" scoped>
.tl {
  display: flex;
  margin: 0;
  padding: 0;
  list-style: none;
}

.tl--vertical {
  flex-direction: column;
}

.tl__item {
  display: flex;
  min-width: 0;
}

.tl--vertical .tl__item {
  gap: 12px;
}

.tl--horizontal .tl__item {
  flex: 1;
  flex-direction: column;
  gap: 10px;
}

// ── Riel: punto + línea ──
.tl__rail {
  display: flex;
  flex-shrink: 0;
  align-items: center;
}

.tl--vertical .tl__rail {
  flex-direction: column;
}

.tl__punto {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  transition: background 0.2s ease, box-shadow 0.2s ease;
}

.tl__linea {
  flex: 1;
  background: var(--app-border-control);

  &--activa {
    background: var(--q-primary);
  }
}

.tl--vertical .tl__linea {
  width: 2px;
  min-height: 16px;
  margin: 4px 0;
}

.tl--horizontal .tl__linea {
  height: 2px;
  margin: 0 8px;
}

// ── Colores del punto ──
// Hecho: tinte suave. Actual: relleno sólido y un halo. Pendiente: hueco.
.tl__punto--primary {
  background: var(--app-brand-soft);
  color: var(--app-brand-soft-ink);
}

.tl__punto--neutral {
  background: var(--app-page);
  color: var(--app-ink-2);
}

@each $name in (positive, warning, negative, info) {
  .tl__punto--#{$name} {
    background: var(--app-tint-#{$name});
    color: var(--app-ink-#{$name});
  }

  .tl__item--actual .tl__punto--#{$name} {
    background: var(--app-ink-#{$name});
    box-shadow: 0 0 0 4px var(--app-tint-#{$name});
    color: var(--app-surface);
  }
}

.tl__item--actual .tl__punto--primary {
  background: var(--q-primary);
  box-shadow: 0 0 0 4px var(--app-brand-soft);
  color: var(--app-on-primary);
}

.tl__item--actual .tl__punto--neutral {
  background: var(--app-ink-2);
  box-shadow: 0 0 0 4px var(--app-page);
  color: var(--app-surface);
}

.tl__item--pendiente .tl__punto {
  background: transparent;
  box-shadow: inset 0 0 0 2px var(--app-border-control);
  color: var(--app-ink-2);
}

// ── Texto ──
.tl__cuerpo {
  min-width: 0;
}

.tl--vertical .tl__cuerpo {
  flex: 1;
  padding: 5px 0 18px;
}

.tl--vertical .tl__item:last-child .tl__cuerpo {
  padding-bottom: 0;
}

.tl--horizontal .tl__cuerpo {
  padding-right: 12px;
}

.tl__fecha {
  margin-bottom: 2px;
  font-size: 12px;
  color: var(--app-ink-2);
}

.tl__titulo {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.35;
  color: var(--app-ink);
}

.tl__item--pendiente .tl__titulo {
  font-weight: 500;
  color: var(--app-ink-2);
}

.tl__extra {
  flex-shrink: 0;
  font-weight: 600;
}

.tl__desc {
  margin: 3px 0 0;
  font-size: 13px;
  line-height: 1.45;
  color: var(--app-ink-2);
  white-space: pre-line;
}
</style>
