<template>
  <q-page>
    <ComprasList />
  </q-page>
</template>

<script setup>
import ComprasList from '@/modules/Compras/ComprasList.vue'

definePage({ meta: { permiso: 'compras.index' } })
</script>
