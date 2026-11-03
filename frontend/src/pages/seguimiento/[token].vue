<template>
  <main class="seg">
    <div class="seg__card">
      <header class="seg__marca">
        <AppLogo :alto="44" />
      </header>

      <div
        v-if="cargando"
        class="seg__centro"
      >
        <q-spinner size="28px" />
      </div>

      <div
        v-else-if="!orden"
        class="seg__centro seg__vacio"
      >
        <q-icon
          name="search_off"
          size="40px"
        />
        <p>No encontramos esta orden. Revise el enlace o comuníquese con nosotros.</p>
      </div>

      <template v-else>
        <div class="seg__cabecera">
          <div>
            <div class="seg__codigo text-mono">
              {{ orden.codigo }}
            </div>
            <div class="seg__equipo">
              {{ orden.equipo }}
            </div>
          </div>
          <AppChip
            :status="ESTADOS[orden.estado]?.status ?? 'info'"
            :label="orden.estado_label"
          />
        </div>

        <!-- Los pasos del taller: dónde está el equipo ahora. -->
        <ol class="seg__pasos">
          <li
            v-for="(paso, i) in PASOS"
            :key="paso.estado"
            :class="['seg__paso', {
              'seg__paso--hecho': i < pasoActual,
              'seg__paso--actual': i === pasoActual
            }]"
          >
            <span class="seg__punto">
              <q-icon
                :name="i < pasoActual ? 'check' : ESTADOS[paso.estado].icon"
                size="16px"
              />
            </span>
            <span>{{ paso.label }}</span>
          </li>
        </ol>

        <p class="seg__mensaje">
          {{ mensaje }}
        </p>

        <dl class="seg__datos">
          <dt>Recibido</dt>
          <dd>{{ formatearFechaHora(orden.recibido_at) }}</dd>
          <template v-if="orden.entregado_at">
            <dt>Entregado</dt>
            <dd>{{ formatearFechaHora(orden.entregado_at) }}</dd>
          </template>
          <template v-if="orden.garantia_hasta">
            <dt>Garantía hasta</dt>
            <dd>{{ fechaCorta(orden.garantia_hasta) }}</dd>
          </template>
          <template v-if="Number(orden.total) > 0">
            <dt>Total</dt>
            <dd class="text-mono">
              {{ formatearPrecio(orden.total) }}
            </dd>
            <dt>Saldo</dt>
            <dd class="text-mono seg__saldo">
              {{ formatearPrecio(orden.saldo) }}
            </dd>
          </template>
        </dl>

        <footer
          v-if="orden.sede"
          class="seg__sede"
        >
          <strong>{{ orden.sede.nombre }}</strong>
          <span v-if="orden.sede.direccion">{{ orden.sede.direccion }}</span>
          <a
            v-if="orden.sede.telefono"
            :href="`tel:${orden.sede.telefono}`"
          >
            <q-icon name="call" /> {{ orden.sede.telefono }}
          </a>
        </footer>
      </template>
    </div>
  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import AppChip from '@/components/AppChip.vue'
import { ESTADOS, fechaCorta } from '@/modules/OrdenesServicio/constantes'
import OrdenServicioService from '@/services/OrdenServicioService'
import { formatearFechaHora } from '@/utils/fechas'
import { formatearPrecio } from '@/utils/moneda'

// Página pública (la abre el QR del ticket): sin sesión ni menú.

const PASOS = [
  { estado: 'recibido', label: 'Recibido' },
  { estado: 'diagnostico', label: 'Diagnóstico' },
  { estado: 'en_reparacion', label: 'En reparación' },
  { estado: 'reparado', label: 'Listo para recoger' },
  { estado: 'entregado', label: 'Entregado' }
]

// En qué paso cae cada estado (los que no son del camino feliz, en el más cercano).
const PASO_DE = {
  recibido: 0,
  diagnostico: 1,
  esperando_repuesto: 2,
  en_reparacion: 2,
  reparado: 3,
  sin_reparacion: 3,
  abandonado: 3,
  entregado: 4
}

const MENSAJES = {
  recibido: 'Recibimos su equipo. Pronto empezaremos a revisarlo.',
  diagnostico: 'Un técnico está revisando su equipo.',
  esperando_repuesto: 'Estamos esperando un repuesto para continuar con la reparación.',
  en_reparacion: 'Su equipo se está reparando.',
  reparado: '¡Su equipo está listo! Puede pasar a recogerlo.',
  sin_reparacion: 'No fue posible reparar su equipo. Puede pasar a recogerlo.',
  abandonado: 'Su equipo sigue en el taller. Comuníquese con nosotros para coordinar el retiro.',
  entregado: 'Su equipo fue entregado. ¡Gracias por su confianza!'
}

const route = useRoute()
const orden = ref(null)
const cargando = ref(true)

const pasoActual = computed(() => PASO_DE[orden.value?.estado] ?? 0)
const mensaje = computed(() => MENSAJES[orden.value?.estado] ?? '')

onMounted(async () => {
  try {
    orden.value = await OrdenServicioService.seguimiento(route.params.token)
  } catch {
    orden.value = null
  } finally {
    cargando.value = false
  }
})
</script>

<style lang="scss" scoped>
.seg {
  display: flex;
  justify-content: center;
  min-height: 100vh;
  padding: 24px 16px;
  background: var(--app-page);
}

.seg__card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  width: 100%;
  max-width: 480px;
  height: fit-content;
  padding: 24px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 16px;
  background: var(--app-surface);
  box-shadow: var(--app-shadow-card);
  color: var(--app-ink);
}

.seg__marca {
  display: flex;
  align-items: center;
  gap: 10px;
}


.seg__centro {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px 0;
  text-align: center;
}

.seg__vacio {
  color: var(--app-ink-2);
}

.seg__cabecera {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.seg__codigo {
  font-size: 20px;
  font-weight: 800;
}

.seg__equipo {
  font-size: 14px;
  color: var(--app-ink-2);
}

.seg__pasos {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.seg__paso {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--app-ink-2);
}

.seg__punto {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: 2px solid var(--app-border-control);
  border-radius: 999px;
}

.seg__paso--hecho {
  color: var(--app-ink);

  .seg__punto {
    border-color: var(--app-ink-positive);
    background: var(--app-tint-positive);
    color: var(--app-ink-positive);
  }
}

.seg__paso--actual {
  font-weight: 700;
  color: var(--app-ink);

  .seg__punto {
    border-color: $primary-hover;
    background: $primary-hover;
    color: #FFFFFF;
  }
}

.seg__mensaje {
  margin: 0;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--app-brand-soft);
  font-size: 14px;
  font-weight: 600;
  line-height: 1.5;
  color: var(--app-brand-soft-ink);
}

.seg__datos {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 6px 16px;
  margin: 0;
  font-size: 13.5px;

  dt {
    color: var(--app-ink-2);
  }

  dd {
    margin: 0;
    text-align: right;
  }
}

.seg__saldo {
  font-weight: 800;
}

.seg__sede {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-top: 14px;
  border-top: 1px solid var(--app-border-subtle);
  font-size: 13px;
  color: var(--app-ink-2);

  strong {
    color: var(--app-ink);
  }

  a {
    color: $primary-hover;
    font-weight: 600;
    text-decoration: none;
  }
}
</style>
