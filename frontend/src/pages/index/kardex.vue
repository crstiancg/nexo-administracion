<template>
  <q-page>
    <KardexPanel />
  </q-page>
</template>

<script setup>
import KardexPanel from '@/modules/Inventario/KardexPanel.vue'

definePage({ meta: { permiso: 'reportes.inventario' } })
</script>
