<template>
  <q-page>
    <ReportesPanel />
  </q-page>
</template>

<script setup>
import ReportesPanel from '@/modules/Reportes/ReportesPanel.vue'

definePage({ meta: { permiso: 'reportes.ventas' } })
</script>
