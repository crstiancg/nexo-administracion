<template>
  <q-page>
    <OrdenesCompraList />
  </q-page>
</template>

<script setup>
import OrdenesCompraList from '@/modules/OrdenesCompra/OrdenesCompraList.vue'

definePage({ meta: { permiso: 'ordenes-compra.index' } })
</script>
