<template>
  <q-btn
    flat
    dense
    round
    icon="notifications_none"
    class="alertas"
    :aria-label="alertas.length ? `Alertas: ${alertas.length}` : 'Alertas: nada pendiente'"
  >
    <!-- Cifra sólo para lo que urge (crítico + aviso); lo de rutina no suma. -->
    <AppBadge
      v-if="urgentes"
      floating
      class="alertas__cifra"
    >
      {{ urgentes > 9 ? '9+' : urgentes }}
    </AppBadge>

    <q-menu
      anchor="bottom right"
      self="top right"
      :offset="[0, 8]"
      @before-show="cargar"
    >
      <div class="alertas__panel">
        <div class="alertas__cabecera">
          <span>Alertas</span>
          <span
            v-if="alertas.length"
            class="alertas__cuenta"
          >{{ alertas.length }}</span>
        </div>

        <div
          v-if="!alertas.length"
          class="alertas__vacio"
        >
          <q-icon
            name="check_circle"
            size="28px"
          />
          Todo en orden en {{ userStore.sede?.nombre ?? 'tu sede' }}.
        </div>

        <router-link
          v-for="alerta in alertas"
          :key="alerta.clave"
          v-close-popup
          :to="alerta.to"
          :class="['alertas__item', `alertas__item--${alerta.nivel}`]"
        >
          <q-icon
            :name="NIVELES[alerta.nivel].icono"
            size="20px"
            class="alertas__icono"
          />
          <span class="alertas__texto">
            <span class="alertas__titulo">{{ alerta.titulo }}</span>
            <span class="alertas__detalle">{{ alerta.detalle }}</span>
          </span>
          <span class="alertas__nivel">{{ NIVELES[alerta.nivel].label }}</span>
        </router-link>
      </div>
    </q-menu>
  </q-btn>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppBadge from '@/components/AppBadge.vue'
import PanelService from '@/services/PanelService'
import { useUserStore } from '@/stores/user-store'

/**
 * La campana del toolbar: lo que necesita atención en la sede (caja sin
 * cerrar, lotes vencidos, stock bajo el mínimo, pedidos pendientes…). Se
 * recalcula al navegar y cada 2 minutos.
 */
const NIVELES = {
  // El nivel no se comunica sólo con color: ícono y palabra.
  critical: { icono: 'error', label: 'Urgente' },
  warning: { icono: 'warning', label: 'Atención' },
  info: { icono: 'info', label: 'Pendiente' }
}

const route = useRoute()
const userStore = useUserStore()
const alertas = ref([])

const urgentes = computed(() => alertas.value.filter((a) => a.nivel !== 'info').length)

async function cargar () {
  try {
    alertas.value = await PanelService.alertas()
  } catch {
    // Una campana no vale un error en pantalla.
  }
}

watch(() => route.path, cargar)
onMounted(cargar)

const reloj = setInterval(cargar, 120_000)
onBeforeUnmount(() => clearInterval(reloj))

defineExpose({ cargar })
</script>

<style lang="scss" scoped>
.alertas {
  color: var(--app-ink-2);
}

.alertas__panel {
  width: 360px;
  max-width: calc(100vw - 32px);
  background: var(--app-surface);
  color: var(--app-ink);
}

.alertas__cabecera {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 16px 10px;
  border-bottom: 1px solid var(--app-border-subtle);
  font-size: 15px;
  font-weight: 700;
}

.alertas__cuenta {
  padding: 0 7px;
  border-radius: 999px;
  background: var(--app-border-subtle);
  font-size: 12px;
  line-height: 20px;
  color: var(--app-ink-2);
}

.alertas__vacio {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 28px 16px;
  font-size: 13px;
  color: var(--app-ink-2);

  .q-icon {
    color: var(--q-positive);
  }
}

.alertas__item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--app-border-subtle);
  color: inherit;
  text-decoration: none;

  &:last-child {
    border-bottom: 0;
  }

  &:hover {
    background: var(--app-page);
  }

  &--critical .alertas__icono { color: var(--q-negative); }
  &--warning .alertas__icono { color: var(--q-warning); }
  &--info .alertas__icono { color: var(--q-info); }
}

.alertas__texto {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.alertas__titulo {
  font-size: 14px;
  font-weight: 600;
}

.alertas__detalle {
  font-size: 12px;
  line-height: 1.4;
  color: var(--app-ink-2);
}

.alertas__nivel {
  flex-shrink: 0;
  font-size: 11px;
  font-weight: 600;
  color: var(--app-ink-2);
}
</style>
