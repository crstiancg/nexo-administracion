<template>
  <!-- Las series de lo que SALE: cuáles se entregan, de las que hay en la
       sede. Vacío = salen las que entraron primero. -->
  <q-select
    :model-value="modelValue"
    :options="filtradas"
    multiple
    use-chips
    use-input
    input-debounce="0"
    dense
    outlined
    hide-bottom-space
    no-error-icon
    :max-values="cantidadEntera || undefined"
    :label="`Series a entregar (${modelValue.length} de ${cantidadEntera})`"
    :hint="modelValue.length ? '' : 'Vacío: salen las primeras que entraron'"
    :loading="cargando"
    :error="Boolean(error)"
    :error-message="error"
    class="series-select"
    @filter="filtrar"
    @update:model-value="(v) => emit('update:modelValue', v)"
  >
    <template #no-option>
      <q-item>
        <q-item-section class="text-grey-7">
          {{ cargando ? 'Cargando…' : 'No hay series en stock en esta sede.' }}
        </q-item-section>
      </q-item>
    </template>
  </q-select>
</template>

<script setup>
import { computed, ref } from 'vue'
import { api } from '@/boot/axios'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  varianteId: {
    type: Number,
    required: true
  },
  // Por defecto, la sede del usuario.
  sedeId: {
    type: Number,
    default: null
  },
  cantidad: {
    type: [Number, String],
    default: 0
  },
  error: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue'])

const disponibles = ref(null)
const cargando = ref(false)
const busqueda = ref('')
const cantidadEntera = computed(() => Math.max(0, Math.round(Number(props.cantidad) || 0)))

const filtradas = computed(() => (disponibles.value ?? [])
  .filter((n) => !busqueda.value || n.includes(busqueda.value.toUpperCase())))

async function filtrar (valor, listo) {
  busqueda.value = valor
  if (disponibles.value === null && !cargando.value) {
    cargando.value = true
    try {
      const { data } = (await api.get('api/series', {
        params: { variante_id: props.varianteId, sede_id: props.sedeId ?? undefined, estado: 'en_stock' }
      })).data
      disponibles.value = data.map((s) => s.numero)
    } catch {
      disponibles.value = []
    } finally {
      cargando.value = false
    }
  }
  listo()
}
</script>

<style lang="scss" scoped>
.series-select {
  min-width: 220px;
}
</style>
