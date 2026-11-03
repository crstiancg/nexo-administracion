<template>
  <form
    class="sede-form"
    novalidate
    @submit.prevent="submit"
  >
    <AppTextField
      v-model="form.sede.nombre"
      label="Nombre"
      icon="storefront"
      placeholder="Lima - Surquillo, Arequipa…"
      maxlength="60"
      :error="form.errors[`${PATH}.nombre`]"
      :loading="form.validating"
      autofocus
      @change="form.validate(`${PATH}.nombre`)"
    />

    <AppTextField
      v-model="form.sede.direccion"
      label="Dirección (opcional)"
      icon="place"
      placeholder="Av. Tomás Marsano 850"
      maxlength="200"
      :error="form.errors[`${PATH}.direccion`]"
      @change="form.validate(`${PATH}.direccion`)"
    />

    <AppTextField
      v-model="form.sede.telefono"
      label="Teléfono (opcional)"
      icon="call"
      placeholder="948 802 191"
      maxlength="30"
      :error="form.errors[`${PATH}.telefono`]"
      @change="form.validate(`${PATH}.telefono`)"
    />

    <AppTextField
      v-model="form.sede.codigo_sunat"
      label="Código de establecimiento SUNAT"
      icon="badge"
      placeholder="0000"
      maxlength="4"
      inputmode="numeric"
      :error="form.errors[`${PATH}.codigo_sunat`]"
      @change="form.validate(`${PATH}.codigo_sunat`)"
    />

    <q-toggle
      v-model="form.sede.activo"
      label="Activa (vende y recibe traslados)"
      color="primary"
      class="sede-form__toggle"
    />
    <p
      v-if="form.errors[`${PATH}.activo`]"
      class="sede-form__error"
      role="alert"
    >
      {{ form.errors[`${PATH}.activo`] }}
    </p>

    <p class="sede-form__hint">
      La dirección y el teléfono salen en el ticket de las ventas de esta sede.
      El código de establecimiento es el de la ficha RUC: 0000 si es el
      domicilio fiscal, o el del anexo (0001…) si es una sucursal.
    </p>

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
import SedeService from '@/services/SedeService'

const PATH = 'sede'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

// Función y no objeto: useForm guarda estos datos como "originales" para el reset().
const inicial = () => ({ sede: { nombre: '', direccion: '', telefono: '', codigo_sunat: '0000', activo: true } })

const form = props.id
  ? useForm('put', `api/sedes/${props.id}`, inicial)
  : useForm('post', 'api/sedes', inicial)

onMounted(async () => {
  if (!props.id) return

  const { nombre, direccion, telefono, codigo_sunat: codigoSunat, activo } = await SedeService.get(props.id)
  form.setData({ [PATH]: { nombre, direccion: direccion ?? '', telefono: telefono ?? '', codigo_sunat: codigoSunat ?? '0000', activo } })
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
.sede-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sede-form__toggle {
  font-size: 13.5px;
  color: var(--app-ink);
}

.sede-form__hint,
.sede-form__error {
  margin: -8px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.sede-form__error {
  color: var(--q-negative);
}
</style>
