<template>
  <q-page>
    <MovimientosList />
  </q-page>
</template>

<script setup>
import MovimientosList from '@/modules/Finanzas/MovimientosList.vue'

definePage({ meta: { permiso: 'movimientos-financieros.index' } })
</script>
