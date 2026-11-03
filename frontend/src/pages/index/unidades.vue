<template>
  <q-page>
    <UnidadesList />
  </q-page>
</template>

<script setup>
import UnidadesList from '@/modules/Unidades/UnidadesList.vue'

definePage({ meta: { permiso: 'unidades.index' } })
</script>
