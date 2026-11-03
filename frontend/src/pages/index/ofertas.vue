<template>
  <q-page>
    <OfertasList />
  </q-page>
</template>

<script setup>
import OfertasList from '@/modules/Ofertas/OfertasList.vue'

definePage({ meta: { permiso: 'ofertas.index' } })
</script>
