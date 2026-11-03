<template>
  <form
    class="rol-form"
    novalidate
    @submit.prevent="submit"
  >
    <AppTextField
      v-model="form.rol.name"
      label="Nombre del rol"
      icon="badge"
      placeholder="Cajero"
      :error="form.errors[`${PATH}.name`]"
      :loading="form.validating"
      autofocus
      @change="form.validate(`${PATH}.name`)"
    />

    <PermisosChecklist
      v-model="form.rol.permisosSelected"
      :permisos="permisos"
      :cargando="cargando"
      :error="form.errors[`${PATH}.permisosSelected`]"
    />

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'
import PermisosChecklist from '@/modules/Permisos/PermisosChecklist.vue'
import PermisoService from '@/services/PermisoService'
import RolService from '@/services/RolService'
import formRol from './FormRol'

const PATH = 'rol'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

const form = props.id
  ? useForm('put', `api/roles/${props.id}`, formRol)
  : useForm('post', 'api/roles', formRol)

const permisos = ref([])
const cargando = ref(true)

onMounted(async () => {
  const [catalogo, rol] = await Promise.all([
    // rowsPerPage 0 = sin paginar: el form necesita el catálogo completo.
    PermisoService.getData({ params: { rowsPerPage: 0, order_by: 'name' } }),
    props.id ? RolService.get(props.id) : null
  ])

  permisos.value = catalogo.data
  cargando.value = false

  if (rol) {
    form.setData({ [PATH]: { name: rol.rol.name, permisosSelected: rol.permisosSelected } })
  }
})

async function submit () {
  try {
    await form.submit()
    form.reset()
    emit('save')
  } catch {
    // 422: los errores quedan en form.errors y se muestran en el form.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.rol-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
</style>
