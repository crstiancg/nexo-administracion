<template>
  <q-page>
    <CotizacionesList />
  </q-page>
</template>

<script setup>
import CotizacionesList from '@/modules/Cotizaciones/CotizacionesList.vue'

definePage({ meta: { permiso: 'cotizaciones.index' } })
</script>
