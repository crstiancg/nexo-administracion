<template>
  <q-page>
    <LotesList />
  </q-page>
</template>

<script setup>
import LotesList from '@/modules/Inventario/LotesList.vue'

definePage({ meta: { permiso: 'inventario.lotes' } })
</script>
