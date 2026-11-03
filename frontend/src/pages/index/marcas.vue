<template>
  <q-page>
    <MarcasList />
  </q-page>
</template>

<script setup>
import MarcasList from '@/modules/Marcas/MarcasList.vue'

definePage({ meta: { permiso: 'marcas.index' } })
</script>
