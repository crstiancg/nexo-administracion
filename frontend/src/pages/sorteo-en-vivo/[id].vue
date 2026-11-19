<template>
  <!-- Fuera del layout: sin menú ni barra, para transmitirlo en vivo. -->
  <SorteoEnVivo :id="Number(route.params.id)" />
</template>

<script setup>
import { useRoute } from 'vue-router'
import SorteoEnVivo from '@/modules/Sorteos/SorteoEnVivo.vue'

definePage({ meta: { permiso: 'sorteos.sortear' } })

const route = useRoute()
</script>
