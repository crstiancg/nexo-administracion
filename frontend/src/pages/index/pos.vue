<template>
  <q-page>
    <PuntoVenta />
  </q-page>
</template>

<script setup>
import PuntoVenta from '@/modules/Ventas/PuntoVenta.vue'

// Sin menú lateral: en mostrador cada píxel es catálogo o carrito.
definePage({ meta: { permiso: 'ventas.store', pantallaCompleta: true } })
</script>
