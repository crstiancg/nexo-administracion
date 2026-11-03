<template>
  <q-page>
    <GananciasPanel />
  </q-page>
</template>

<script setup>
import GananciasPanel from '@/modules/Finanzas/GananciasPanel.vue'

definePage({ meta: { permiso: 'ganancias.index' } })
</script>
