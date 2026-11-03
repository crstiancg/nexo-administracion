<template>
  <form
    class="permiso-form"
    novalidate
    @submit.prevent="submit"
  >
    <!-- El nombre es el de la ruta que protege: se muestra, no se edita. -->
    <div class="permiso-form__ruta">
      <span class="permiso-form__label">Ruta protegida</span>
      <code class="permiso-form__name">{{ nombre || '…' }}</code>
    </div>

    <AppTextField
      v-model="form.permiso.description"
      label="Descripción"
      icon="notes"
      placeholder="Usuarios · Crear"
      :error="form.errors[`${PATH}.description`]"
      :loading="form.validating"
      autofocus
      @change="form.validate(`${PATH}.description`)"
    />

    <!-- Enter en el campo envía el form; el botón visible vive en las
         acciones del diálogo, fuera de este <form>. -->
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
import PermisoService from '@/services/PermisoService'
import formPermiso from './FormPermiso'

const PATH = 'permiso'

// Sólo edición: los permisos se crean desde las rutas, no desde acá.
const props = defineProps({
  id: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['save'])

const form = useForm('put', `api/permisos/${props.id}`, formPermiso)

const nombre = ref('')

onMounted(async () => {
  const permiso = await PermisoService.get(props.id)
  nombre.value = permiso.name
  form.setData({ [PATH]: { description: permiso.description ?? '' } })
})

async function submit () {
  try {
    await form.submit()
    form.reset()
    emit('save')
  } catch {
    // 422: Precognition ya dejó los errores en form.errors y se ven debajo
    // del campo. Otros errores los avisa el interceptor de axios.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.permiso-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.permiso-form__ruta {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.permiso-form__label {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.permiso-form__name {
  align-self: flex-start;
  padding: 4px 10px;
  border-radius: 6px;
  background: var(--app-page);
  font-family: $font-mono;
  font-size: 13px;
  color: var(--app-ink);
}
</style>
