<template>
  <q-page>
    <EtiquetasPanel />
  </q-page>
</template>

<script setup>
import EtiquetasPanel from '@/modules/Etiquetas/EtiquetasPanel.vue'

definePage({ meta: { permiso: 'etiquetas.imprimir' } })
</script>
