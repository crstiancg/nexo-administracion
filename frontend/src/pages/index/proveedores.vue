<template>
  <q-page>
    <ProveedoresList />
  </q-page>
</template>

<script setup>
import ProveedoresList from '@/modules/Proveedores/ProveedoresList.vue'

definePage({ meta: { permiso: 'proveedores.index' } })
</script>
