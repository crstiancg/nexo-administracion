<template>
  <q-page>
    <UsuariosList />
  </q-page>
</template>

<script setup>
import UsuariosList from '@/modules/Usuarios/UsuariosList.vue'

definePage({ meta: { permiso: 'usuarios.index' } })
</script>
