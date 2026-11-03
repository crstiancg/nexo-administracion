<script setup>
import { useQuasar } from "quasar";
import { ref, onMounted } from "vue";

const $q = useQuasar();

//Estados reactivos
const darkMode = ref($q.dark.isActive);

//OnMounted
onMounted(() => {
  // console.log(darkMode.value);
});

//Métodos
function changeDarkMode() {
  $q.dark.set(darkMode.value);
}
</script>

<template>
  <q-toggle
    dense
    v-model="darkMode"
    :color="'primary'"
    @update:model-value="changeDarkMode()"
    size="lg"
    checked-icon="mdi-moon-waxing-crescent"
    unchecked-icon="mdi-white-balance-sunny"
    :aria-label="darkMode ? 'Desactivar modo oscuro' : 'Activar modo oscuro'"
  >
  </q-toggle>
</template>