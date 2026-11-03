<template>
  <q-page>
    <CajasHistorial />
  </q-page>
</template>

<script setup>
import CajasHistorial from '@/modules/Caja/CajasHistorial.vue'

definePage({ meta: { permiso: 'cajas.index' } })
</script>
