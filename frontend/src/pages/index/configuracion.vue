<template>
  <q-page>
    <ConfiguracionPanel />
  </q-page>
</template>

<script setup>
import ConfiguracionPanel from '@/modules/Configuracion/ConfiguracionPanel.vue'

definePage({ meta: { permiso: 'configuracion.update' } })
</script>
