<template>
  <q-page>
    <SorteosList />
  </q-page>
</template>

<script setup>
import SorteosList from '@/modules/Sorteos/SorteosList.vue'

definePage({ meta: { permiso: 'sorteos.index' } })
</script>
