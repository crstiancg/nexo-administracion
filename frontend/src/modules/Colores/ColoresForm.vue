<template>
  <form
    class="color-form"
    novalidate
    @submit.prevent="submit"
  >
    <AppTextField
      v-model="form.color.nombre"
      label="Nombre"
      icon="label_outline"
      placeholder="Rojo marca"
      :error="form.errors[`${PATH}.nombre`]"
      autofocus
      @change="form.validate(`${PATH}.nombre`)"
    />

    <div class="color-form__hex">
      <AppTextField
        v-model="form.color.hexadecimal"
        label="Hexadecimal"
        icon="tag"
        placeholder="#E30613"
        maxlength="7"
        class="color-form__hexField"
        :error="form.errors[`${PATH}.hexadecimal`]"
        :loading="form.validating"
        @change="form.validate(`${PATH}.hexadecimal`)"
      />

      <!-- Muestra + selector nativo: se puede tipear el código o elegirlo. -->
      <label
        :class="['color-form__swatch', { 'color-form__swatch--vacio': !hex }]"
        :style="hex ? { background: hex } : {}"
        title="Elegir color"
      >
        <input
          type="color"
          class="color-form__picker"
          :value="hex ?? '#000000'"
          aria-label="Elegir color"
          @input="elegir($event.target.value)"
        >
      </label>
    </div>

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'
import ColorService from '@/services/ColorService'
import { normalizarHex } from '@/utils/color'
import formColor from './FormColor'

const PATH = 'color'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

const form = props.id
  ? useForm('put', `api/colores/${props.id}`, formColor)
  : useForm('post', 'api/colores', formColor)

// null mientras lo tipeado no sea un hexadecimal válido.
const hex = computed(() => normalizarHex(form.color.hexadecimal))

function elegir (valor) {
  form.color.hexadecimal = valor.toUpperCase()
  form.validate(`${PATH}.hexadecimal`)
}

onMounted(async () => {
  if (!props.id) return

  const { nombre, hexadecimal } = await ColorService.get(props.id)
  form.setData({ [PATH]: { nombre, hexadecimal } })
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
.color-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.color-form__hex {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.color-form__hexField {
  flex: 1;
}

// Alineada con el input (debajo de la etiqueta del AppTextField).
.color-form__swatch {
  position: relative;
  flex-shrink: 0;
  width: 44px;
  height: 40px;
  margin-top: 26px;
  border: 1px solid var(--app-border-control);
  border-radius: 8px;
  cursor: pointer;
  overflow: hidden;

  &--vacio {
    background:
      linear-gradient(45deg, var(--app-page) 25%, transparent 25%, transparent 75%, var(--app-page) 75%),
      linear-gradient(45deg, var(--app-page) 25%, var(--app-surface) 25%, var(--app-surface) 75%, var(--app-page) 75%);
    background-size: 10px 10px;
    background-position: 0 0, 5px 5px;
  }
}

// El input nativo cubre la muestra pero invisible: el clic abre el selector
// del sistema y se ve nuestra muestra, no el control del navegador.
.color-form__picker {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}
</style>
