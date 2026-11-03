<template>
  <q-page>
    <ClientesList />
  </q-page>
</template>

<script setup>
import ClientesList from '@/modules/Clientes/ClientesList.vue'

definePage({ meta: { permiso: 'clientes.index' } })
</script>
