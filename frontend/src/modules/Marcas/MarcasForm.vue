<template>
  <form
    class="marca-form"
    novalidate
    @submit.prevent="submit"
  >
    <AppTextField
      v-model="form.marca.nombre"
      label="Nombre"
      icon="verified"
      placeholder="Sika, Chema, Z Aditivos…"
      maxlength="60"
      :error="form.errors[`${PATH}.nombre`]"
      :loading="form.validating"
      autofocus
      @change="form.validate(`${PATH}.nombre`)"
    />

    <q-toggle
      v-model="form.marca.activo"
      label="Activa (se puede elegir en productos nuevos)"
      color="primary"
      class="marca-form__toggle"
    />

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { onMounted } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'
import MarcaService from '@/services/MarcaService'

const PATH = 'marca'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

// Función y no objeto: useForm guarda estos datos como "originales" para el reset().
const inicial = () => ({ marca: { nombre: '', activo: true } })

const form = props.id
  ? useForm('put', `api/marcas/${props.id}`, inicial)
  : useForm('post', 'api/marcas', inicial)

onMounted(async () => {
  if (!props.id) return

  const { nombre, activo } = await MarcaService.get(props.id)
  form.setData({ [PATH]: { nombre, activo } })
})

async function submit () {
  try {
    await form.submit()
    form.reset()
    emit('save')
  } catch {
    // 422: los errores quedan en form.errors y se ven debajo de cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.marca-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.marca-form__toggle {
  font-size: 13.5px;
  color: var(--app-ink);
}
</style>
