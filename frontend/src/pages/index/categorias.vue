<template>
  <q-page>
    <CategoriasList />
  </q-page>
</template>

<script setup>
import CategoriasList from '@/modules/Categorias/CategoriasList.vue'

definePage({ meta: { permiso: 'categorias.index' } })
</script>
