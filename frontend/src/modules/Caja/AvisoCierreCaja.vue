<template>
  <div
    v-if="alerta"
    :class="['cierre-caja', `cierre-caja--${urgente ? 'urgente' : 'aviso'}`]"
    :role="urgente ? 'alert' : 'status'"
  >
    <q-icon
      :name="urgente ? 'alarm' : 'schedule'"
      size="22px"
      class="cierre-caja__icono"
    />
    <div class="cierre-caja__texto">
      <strong>{{ alerta.titulo }}</strong>
      <span>{{ alerta.detalle }}</span>
    </div>
    <AppButton
      v-if="route.path !== alerta.to"
      :variant="urgente ? 'destructive' : 'primary'"
      label="Cerrar caja"
      icon="lock"
      :to="alerta.to"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '@/components/AppButton.vue'

/**
 * Barra fija arriba de todas las pantallas en el horario de cierre de caja
 * (Configuración > Caja): ámbar desde la hora de aviso, roja pasada la hora
 * límite. Sale de la alerta `caja_cierre` de la campana (App\Services\Alertas),
 * que sólo llega a quien puede cerrar la caja. Se va al cerrarla.
 */
const props = defineProps({
  // { nivel, titulo, detalle, to } o null.
  alerta: {
    type: Object,
    default: null
  }
})

const route = useRoute()
const urgente = computed(() => props.alerta?.nivel === 'critical')
</script>

<style lang="scss" scoped>
.cierre-caja {
  position: sticky;
  // Debajo del header fijo (64px + borde), como el punto de venta.
  top: var(--app-header-height, 65px);
  z-index: 5;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 32px;
  border-bottom: 1px solid;
  font-size: 13.5px;
  line-height: 1.4;

  // Mismos tintes que AppChip: cambian solos con el tema.
  &--aviso {
    border-color: var(--app-tint-warning);
    background: var(--app-tint-warning);
    color: var(--app-ink-warning);
  }

  &--urgente {
    border-color: var(--app-negative-border);
    background: var(--app-tint-negative);
    color: var(--app-ink-negative);
  }
}

.cierre-caja__icono {
  flex: none;
}

.cierre-caja__texto {
  display: flex;
  flex: 1;
  flex-wrap: wrap;
  gap: 2px 8px;
  min-width: 0;

  strong {
    font-weight: 700;
  }
}

@media (max-width: 599px) {
  .cierre-caja {
    flex-wrap: wrap;
    padding: 10px 16px;
  }

  .cierre-caja__texto {
    flex-basis: calc(100% - 34px);
  }
}
</style>
