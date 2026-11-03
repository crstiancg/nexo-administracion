<template>
  <div class="conceptos">
    <div
      class="conceptos__tabs"
      role="tablist"
    >
      <button
        v-for="t in TIPOS"
        :key="t.value"
        type="button"
        role="tab"
        :aria-selected="String(tipo === t.value)"
        :class="['conceptos__tab', { 'conceptos__tab--activo': tipo === t.value }]"
        @click="tipo = t.value"
      >
        {{ t.label }}
      </button>
    </div>

    <form
      v-if="userStore.hasPermission('conceptos-financieros.store')"
      class="conceptos__nuevo"
      @submit.prevent="crear"
    >
      <q-input
        v-model="nuevo"
        dense
        outlined
        maxlength="80"
        :placeholder="tipo === 'gasto' ? 'Nuevo concepto de gasto…' : 'Nuevo concepto de ingreso…'"
        :error="Boolean(error)"
        :error-message="error"
        hide-bottom-space
        class="conceptos__input"
      />
      <q-btn
        type="submit"
        unelevated
        no-caps
        color="primary"
        icon="add"
        label="Agregar"
        :disable="!nuevo.trim()"
      />
    </form>

    <ul class="conceptos__lista">
      <li
        v-for="c in deTipo"
        :key="c.id"
        :class="{ 'conceptos__item--inactivo': !c.activo }"
      >
        <span class="conceptos__nombre">{{ c.nombre }}</span>
        <span class="conceptos__uso">{{ c.movimientos_count }} mov.</span>
        <q-toggle
          v-if="userStore.hasPermission('conceptos-financieros.update')"
          :model-value="c.activo"
          dense
          color="primary"
          :aria-label="`${c.activo ? 'Desactivar' : 'Activar'} ${c.nombre}`"
          @update:model-value="alternar(c)"
        />
        <q-btn
          v-if="userStore.hasPermission('conceptos-financieros.destroy') && !c.movimientos_count"
          flat
          dense
          round
          size="sm"
          icon="delete_outline"
          color="negative"
          :aria-label="`Eliminar ${c.nombre}`"
          @click="eliminar(c)"
        />
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import FinanzaService from '@/services/FinanzaService'
import { useUserStore } from '@/stores/user-store'

/**
 * Los conceptos con que se clasifican gastos e ingresos. Uno con movimientos
 * no se borra: se desactiva (deja de ofrecerse, sigue en lo ya cargado).
 */
const props = defineProps({
  tipoInicial: {
    type: String,
    default: 'gasto'
  }
})

const emit = defineEmits(['cambio'])

const TIPOS = [
  { value: 'gasto', label: 'Gastos' },
  { value: 'ingreso', label: 'Ingresos' }
]

const userStore = useUserStore()
const tipo = ref(props.tipoInicial)
const conceptos = ref([])
const nuevo = ref('')
const error = ref('')

const deTipo = computed(() => conceptos.value.filter((c) => c.tipo === tipo.value))

async function cargar () {
  conceptos.value = (await FinanzaService.conceptos({ params: { rowsPerPage: 0, order_by: 'nombre' } })).data
}
onMounted(cargar)

async function crear () {
  error.value = ''
  try {
    await FinanzaService.guardarConcepto({ tipo: tipo.value, nombre: nuevo.value, activo: true })
    nuevo.value = ''
    await cargar()
    emit('cambio')
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo agregar.'
  }
}

async function alternar (c) {
  await FinanzaService.guardarConcepto({ id: c.id, tipo: c.tipo, nombre: c.nombre, activo: !c.activo })
  await cargar()
  emit('cambio')
}

async function eliminar (c) {
  await FinanzaService.eliminarConcepto(c.id)
  await cargar()
  emit('cambio')
}
</script>

<style lang="scss" scoped>
.conceptos {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.conceptos__tabs {
  display: inline-flex;
  align-self: flex-start;
  padding: 3px;
  border: 1px solid var(--app-border-control);
  border-radius: 9px;
  background: var(--app-page);
}

.conceptos__tab {
  padding: 5px 14px;
  border: 0;
  border-radius: 6px;
  background: none;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &--activo {
    background: var(--app-surface);
    box-shadow: var(--app-shadow-card);
    color: var(--app-ink);
  }
}

.conceptos__nuevo {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.conceptos__input {
  flex: 1;
}

.conceptos__lista {
  max-height: 340px;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;

  li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 2px;
    border-top: 1px solid var(--app-border-subtle);
    font-size: 13.5px;
  }
}

.conceptos__nombre {
  flex: 1;
  min-width: 0;
  color: var(--app-ink);
}

.conceptos__uso {
  font-size: 12px;
  color: var(--app-ink-2);
}

.conceptos__item--inactivo .conceptos__nombre {
  color: var(--app-ink-2);
  text-decoration: line-through;
}
</style>
