<script setup>
import { computed } from 'vue'
import { useQuasar } from 'quasar'
import { TEMA } from '@/config/tema'

const $q = useQuasar()

// El modo vive en el tema (se guarda y lo comparte el menú de la paleta):
// el switch sólo lo lee y lo cambia.
const darkMode = computed({
  get: () => $q.dark.isActive,
  set: (oscuro) => {
    TEMA.modo = oscuro ? 'oscuro' : 'claro'
    $q.dark.set(oscuro)
  }
})
</script>

<template>
  <q-toggle
    v-model="darkMode"
    dense
    color="primary"
    size="lg"
    checked-icon="mdi-moon-waxing-crescent"
    unchecked-icon="mdi-white-balance-sunny"
    :aria-label="darkMode ? 'Desactivar modo oscuro' : 'Activar modo oscuro'"
  />
</template>
