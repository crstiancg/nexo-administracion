<template>
  <q-page>
    <PedidosList />
  </q-page>
</template>

<script setup>
import PedidosList from '@/modules/Pedidos/PedidosList.vue'

definePage({ meta: { permiso: 'pedidos.index' } })
</script>
