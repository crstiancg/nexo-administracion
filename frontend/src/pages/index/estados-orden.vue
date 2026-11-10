<template>
  <q-page>
    <EstadosOrdenList />
  </q-page>
</template>

<script setup>
import EstadosOrdenList from '@/modules/EstadosOrden/EstadosOrdenList.vue'

definePage({ meta: { permiso: 'estados-orden.index' } })
</script>
