<template>
  <q-page>
    <SedesList />
  </q-page>
</template>

<script setup>
import SedesList from '@/modules/Sedes/SedesList.vue'

definePage({ meta: { permiso: 'sedes.index' } })
</script>
