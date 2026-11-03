<template>
  <!-- Con permiso para cambiarse, un menú; si no, sólo dice dónde está. -->
  <q-btn
    v-if="puedeCambiar"
    flat
    no-caps
    dense
    class="sede-selector"
    :aria-label="`Sede: ${nombre}. Cambiar de sede`"
  >
    <q-icon
      name="storefront"
      size="18px"
    />
    <span class="sede-selector__nombre">{{ nombre }}</span>
    <q-icon
      name="expand_more"
      size="18px"
    />

    <q-menu
      anchor="bottom right"
      self="top right"
      @before-show="cargar"
    >
      <q-list
        dense
        class="sede-selector__lista"
      >
        <q-item-label header>
          Operar en
        </q-item-label>
        <q-item
          v-if="cargando"
          class="text-grey"
        >
          <q-item-section>Cargando…</q-item-section>
        </q-item>
        <q-item
          v-for="sede in sedes"
          :key="sede.id"
          v-close-popup
          clickable
          :active="sede.id === userStore.sedeId"
          @click="elegir(sede)"
        >
          <q-item-section>{{ sede.nombre }}</q-item-section>
          <q-item-section
            v-if="sede.id === userStore.sedeId"
            side
          >
            <q-icon
              name="check"
              size="16px"
            />
          </q-item-section>
        </q-item>
      </q-list>
    </q-menu>
  </q-btn>

  <div
    v-else
    class="sede-selector sede-selector--fija"
  >
    <q-icon
      name="storefront"
      size="18px"
    />
    <span class="sede-selector__nombre">{{ nombre }}</span>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useQuasar } from 'quasar'
import SedeService from '@/services/SedeService'
import { useUserStore } from '@/stores/user-store'

const $q = useQuasar()
const userStore = useUserStore()

const puedeCambiar = computed(() => userStore.hasPermission('auth.cambiar-sede'))
const nombre = computed(() => userStore.sede?.nombre ?? 'Sin sede')

const sedes = ref([])
const cargando = ref(false)

async function cargar () {
  cargando.value = true
  try {
    sedes.value = await SedeService.activas()
  } finally {
    cargando.value = false
  }
}

// Cambiar de sede cambia el stock, la caja y el catálogo de toda pantalla
// abierta: se recarga entera en vez de avisarle a cada una.
async function elegir (sede) {
  if (sede.id === userStore.sedeId) return
  try {
    await userStore.cambiarSede(sede.id)
    window.location.reload()
  } catch (error) {
    $q.notify({ type: 'negative', message: error.response?.data?.message ?? 'No se pudo cambiar de sede.', position: 'top-right' })
  }
}
</script>

<style lang="scss" scoped>
.sede-selector {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border: 1px solid var(--app-border-control);
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);

  &--fija {
    border-style: dashed;
  }
}

.sede-selector__nombre {
  max-width: 160px;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.sede-selector__lista {
  min-width: 200px;
}
</style>
