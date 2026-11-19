<template>
  <q-page>
    <SorteoDetalle
      :id="Number(route.params.id)"
      :key="route.params.id"
    />
  </q-page>
</template>

<script setup>
import { useRoute } from 'vue-router'
import SorteoDetalle from '@/modules/Sorteos/SorteoDetalle.vue'

definePage({ meta: { permiso: 'sorteos.index' } })

const route = useRoute()
</script>
