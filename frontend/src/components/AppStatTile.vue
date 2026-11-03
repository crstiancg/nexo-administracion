<template>
  <AppCard
    :variant="props.featured ? 'highlight' : 'surface'"
    class="app-stat"
  >
    <div :class="['app-stat__label', { 'app-stat__label--featured': props.featured }]">
      {{ props.label }}
    </div>

    <div :class="['app-stat__value', { 'app-stat__value--featured': props.featured }]">
      {{ props.value }}
    </div>

    <div
      v-if="props.delta"
      :class="['app-stat__delta', `app-stat__delta--${deltaTone}`]"
    >
      <q-icon
        :name="deltaIcon"
        class="app-stat__arrow"
      />
      <span class="app-stat__deltaValue">{{ props.delta }}</span>
      <span
        v-if="props.deltaCaption"
        class="app-stat__caption"
      >{{ props.deltaCaption }}</span>
    </div>
  </AppCard>
</template>

<script>
export const TRENDS = ['up', 'down']
</script>

<script setup>
import { computed } from 'vue'
import AppCard from './AppCard.vue'

const props = defineProps({
  label: {
    type: String,
    required: true
  },

  // Ya formateado. El tile no formatea números: eso es dominio e i18n.
  value: {
    type: String,
    required: true
  },

  delta: {
    type: String,
    default: ''
  },

  // Hacia dónde se movió la métrica.
  trend: {
    type: String,
    default: 'up',
    validator: (value) => TRENDS.includes(value)
  },

  // Si ese movimiento es bueno. Subir NO siempre es bueno: costos que suben
  // o conversión que baja son la misma flecha con el sentido opuesto.
  trendIsGood: {
    type: Boolean,
    default: true
  },

  deltaCaption: {
    type: String,
    default: ''
  },

  // La cifra hero de la vista. Una sola por pantalla.
  featured: {
    type: Boolean,
    default: false
  }
})

// La FLECHA sigue la dirección del dato. El COLOR sigue si eso es bueno.
// Son dos canales distintos y es justo lo que se suele confundir: pintar de
// verde toda flecha que sube miente cuando lo que sube son los costos.
const deltaIcon = computed(() => (props.trend === 'up' ? 'arrow_upward' : 'arrow_downward'))
const deltaTone = computed(() => (props.trendIsGood ? 'good' : 'bad'))
</script>

<style lang="scss" scoped>
.app-stat {
  padding: 18px 20px 16px;
}

.app-stat__label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink-2);
}

.app-stat__label--featured {
  color: var(--app-brand-soft-ink);
  font-weight: 700;
}

// Cifras proporcionales, no tabulares: tabular-nums le da a cada dígito el
// ancho de un 0 y a este tamaño un "121" se ve suelto. El tabular se reserva
// para columnas que tienen que alinearse.
.app-stat__value {
  margin-top: 10px;
  font-size: 25px;
  font-weight: 700;
  letter-spacing: -0.6px;
  line-height: 1;
  color: var(--app-ink);
}

.app-stat__value--featured {
  font-size: 46px;
  letter-spacing: -1.6px;
}

.app-stat__delta {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-top: 10px;
  font-size: 12.5px;
}

.app-stat__arrow {
  font-size: 15px;
}

.app-stat__deltaValue {
  font-weight: 700;
}

// Mismo derivador que los chips, así el verde y el rojo del delta no pueden
// divergir del resto del sistema ni caerse de contraste.
.app-stat__delta--good {
  color: var(--app-ink-positive);
}

.app-stat__delta--bad {
  color: var(--app-ink-negative);
}

// El caption nunca lleva el color del dato: es texto de apoyo.
.app-stat__caption {
  color: var(--app-ink-2);
  font-weight: 500;
}
</style>
