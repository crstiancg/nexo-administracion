<template>
  <q-page>
    <CajaActual />
  </q-page>
</template>

<script setup>
import CajaActual from '@/modules/Caja/CajaActual.vue'

definePage({ meta: { permiso: 'cajas.actual' } })
</script>
