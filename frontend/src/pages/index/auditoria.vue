<template>
  <q-page>
    <AuditoriaList />
  </q-page>
</template>

<script setup>
import AuditoriaList from '@/modules/Auditoria/AuditoriaList.vue'

definePage({ meta: { permiso: 'auditoria.index' } })
</script>
