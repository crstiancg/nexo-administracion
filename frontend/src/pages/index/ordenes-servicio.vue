<template>
  <q-page>
    <OrdenesList />
  </q-page>
</template>

<script setup>
import OrdenesList from '@/modules/OrdenesServicio/OrdenesList.vue'

definePage({ meta: { permiso: 'ordenes-servicio.index' } })
</script>
