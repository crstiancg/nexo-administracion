<template>
  <q-page>
    <PorReponerList />
  </q-page>
</template>

<script setup>
import PorReponerList from '@/modules/Inventario/PorReponerList.vue'

definePage({ meta: { permiso: 'inventario.index' } })
</script>
