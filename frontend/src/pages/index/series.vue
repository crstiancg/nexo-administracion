<template>
  <q-page>
    <SeriesPanel />
  </q-page>
</template>

<script setup>
import SeriesPanel from '@/modules/Inventario/SeriesPanel.vue'

definePage({ meta: { permiso: 'inventario.index' } })
</script>
