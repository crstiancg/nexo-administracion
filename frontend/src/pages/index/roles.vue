<template>
  <q-page>
    <RolesList />
  </q-page>
</template>

<script setup>
import RolesList from '@/modules/Roles/RolesList.vue'

definePage({ meta: { permiso: 'roles.index' } })
</script>
