<template>
  <q-page>
    <MovimientosList />
  </q-page>
</template>

<script setup>
import MovimientosList from '@/modules/Inventario/MovimientosList.vue'

definePage({ meta: { permiso: 'inventario.index' } })
</script>
