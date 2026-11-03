<template>
  <q-page>
    <ProductosList />
  </q-page>
</template>

<script setup>
import ProductosList from '@/modules/Productos/ProductosList.vue'

definePage({ meta: { permiso: 'productos.index' } })
</script>
