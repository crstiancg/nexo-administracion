<template>
  <q-page>
    <ProductoFicha :id="Number(route.params.id)" />
  </q-page>
</template>

<script setup>
import { useRoute } from 'vue-router'
import ProductoFicha from '@/modules/Productos/ProductoFicha.vue'

definePage({ meta: { permiso: 'productos.index' } })

const route = useRoute()
</script>
