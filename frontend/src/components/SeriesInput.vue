<template>
  <!-- Las series de lo que ENTRA: una por línea (el lector de código de
       barras ya manda Enter después de cada una). -->
  <q-input
    :model-value="texto"
    type="textarea"
    autogrow
    dense
    outlined
    hide-bottom-space
    no-error-icon
    :label="`Números de serie (${numeros.length} de ${cantidadEntera})`"
    placeholder="Escanea o escribe una serie por línea"
    :error="Boolean(error) || sobran"
    :error-message="error || (sobran ? `Son ${cantidadEntera}: sobran ${numeros.length - cantidadEntera}.` : '')"
    class="series-input"
    @update:model-value="cambiar"
  />
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  // Los números: ['CND123', 'CND124'].
  modelValue: {
    type: Array,
    default: () => []
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

// El texto se guarda aparte: así un Enter al final no se pierde al escribir.
const texto = ref(props.modelValue.join('\n'))
watch(() => props.modelValue, (valor) => {
  if (valor.join('\n') !== numerosDe(texto.value).join('\n')) texto.value = valor.join('\n')
})

function numerosDe (valor) {
  return String(valor ?? '').split(/[\n,;]+/).map((n) => n.trim().toUpperCase()).filter(Boolean)
}

const numeros = computed(() => numerosDe(texto.value))
const cantidadEntera = computed(() => Math.max(0, Math.round(Number(props.cantidad) || 0)))
const sobran = computed(() => numeros.value.length > cantidadEntera.value)

function cambiar (valor) {
  texto.value = valor ?? ''
  emit('update:modelValue', numerosDe(texto.value))
}
</script>

<style lang="scss" scoped>
.series-input {
  min-width: 220px;

  :deep(textarea) {
    font-family: var(--app-font-mono, monospace);
    font-size: 12.5px;
  }
}
</style>
