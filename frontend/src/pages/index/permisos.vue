<template>
  <q-page>
    <PermisosList />
  </q-page>
</template>

<script setup>
import PermisosList from '@/modules/Permisos/PermisosList.vue'

definePage({ meta: { permiso: 'permisos.index' } })
</script>
