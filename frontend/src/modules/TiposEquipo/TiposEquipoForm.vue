<template>
  <form
    class="tipo-form"
    novalidate
    @submit.prevent="submit"
  >
    <AppTextField
      v-model="form.tipo.nombre"
      label="Nombre"
      icon="devices"
      placeholder="Laptop, Celular, Impresora…"
      maxlength="60"
      :error="form.errors[`${PATH}.nombre`]"
      :loading="form.validating"
      autofocus
      @change="form.validate(`${PATH}.nombre`)"
    />

    <div class="tipo-form__checklist">
      <span class="tipo-form__label">Qué se revisa al recibirlo</span>
      <div
        v-for="(punto, i) in form.tipo.checklist"
        :key="i"
        class="tipo-form__punto"
      >
        <q-input
          v-model="form.tipo.checklist[i]"
          :aria-label="`Punto de revisión ${i + 1}`"
          maxlength="40"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          :error="Boolean(form.errors[`${PATH}.checklist.${i}`])"
          :error-message="form.errors[`${PATH}.checklist.${i}`]"
          class="tipo-form__input"
        />
        <q-btn
          flat
          dense
          round
          icon="close"
          size="sm"
          color="grey-7"
          :aria-label="`Quitar ${punto || 'punto'}`"
          @click="form.tipo.checklist.splice(i, 1)"
        />
      </div>
      <button
        type="button"
        class="tipo-form__agregar"
        @click="form.tipo.checklist.push('')"
      >
        + Agregar punto
      </button>
    </div>

    <q-toggle
      v-model="form.tipo.activo"
      label="Activo (se puede elegir en órdenes nuevas)"
      color="primary"
      class="tipo-form__toggle"
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
import TipoEquipoService from '@/services/TipoEquipoService'

const PATH = 'tipo'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

// Función y no objeto: useForm guarda estos datos como "originales" para el reset().
const inicial = () => ({ tipo: { nombre: '', checklist: ['Enciende'], activo: true } })

const form = props.id
  ? useForm('put', `api/tipos-equipo/${props.id}`, inicial)
  : useForm('post', 'api/tipos-equipo', inicial)

onMounted(async () => {
  if (!props.id) return

  const { nombre, checklist, activo } = await TipoEquipoService.get(props.id)
  form.setData({ [PATH]: { nombre, checklist: checklist ?? [], activo } })
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
.tipo-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.tipo-form__checklist {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.tipo-form__label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink);
}

.tipo-form__punto {
  display: flex;
  align-items: center;
  gap: 6px;
}

.tipo-form__input {
  flex: 1;

  :deep(.q-field__control) {
    border-radius: 10px;
  }
}

.tipo-form__agregar {
  align-self: flex-start;
  padding: 0;
  border: 0;
  background: none;
  font-size: 12.5px;
  font-weight: 600;
  color: $primary-hover;
  cursor: pointer;
}

.tipo-form__toggle {
  font-size: 13.5px;
  color: var(--app-ink);
}
</style>
