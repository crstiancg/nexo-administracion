<template>
  <q-page>
    <TiposEquipoList />
  </q-page>
</template>

<script setup>
import TiposEquipoList from '@/modules/TiposEquipo/TiposEquipoList.vue'

definePage({ meta: { permiso: 'tipos-equipo.index' } })
</script>
