<template>
  <q-page>
    <ColoresList />
  </q-page>
</template>

<script setup>
import ColoresList from '@/modules/Colores/ColoresList.vue'

definePage({ meta: { permiso: 'colores.index' } })
</script>
