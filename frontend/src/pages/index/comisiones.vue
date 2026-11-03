<template>
  <q-page>
    <ComisionesPanel />
  </q-page>
</template>

<script setup>
import ComisionesPanel from '@/modules/Finanzas/ComisionesPanel.vue'

definePage({ meta: { permiso: 'comisiones.index' } })
</script>
