<template>
  <q-page>
    <CreditosList />
  </q-page>
</template>

<script setup>
import CreditosList from '@/modules/Creditos/CreditosList.vue'

definePage({ meta: { permiso: 'creditos.index' } })
</script>
