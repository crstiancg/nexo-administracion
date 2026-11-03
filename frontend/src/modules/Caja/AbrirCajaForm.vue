<template>
  <form
    class="abrir-caja"
    novalidate
    @submit.prevent="submit"
  >
    <AppTextField
      v-model="form.caja.monto_apertura"
      label="Efectivo inicial en el cajón"
      icon="payments"
      type="number"
      min="0"
      step="0.01"
      placeholder="0.00"
      :error="form.errors['caja.monto_apertura'] || errorCaja"
      autofocus
    />
    <p class="abrir-caja__ayuda">
      Contá el sencillo con el que arrancás el día: es la base del arqueo al cerrar.
    </p>
    <AppButton
      type="submit"
      variant="primary"
      label="Abrir caja"
      icon="lock_open"
      :loading="form.processing"
    />
  </form>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppButton from '@/components/AppButton.vue'
import AppTextField from '@/components/AppTextField.vue'

const emit = defineEmits(['save'])

const form = useForm('post', 'api/cajas/abrir', () => ({ caja: { monto_apertura: '' } }))

// 409: otra persona abrió la caja mientras tanto.
const errorCaja = ref('')

async function submit () {
  errorCaja.value = ''
  try {
    const respuesta = await form.submit()
    emit('save', respuesta?.data)
  } catch (error) {
    if (error?.response?.status === 409) {
      errorCaja.value = error.response.data?.errors?.caja?.[0] ?? error.response.data?.message
    }
  }
}
</script>

<style lang="scss" scoped>
.abrir-caja {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 12px;
  max-width: 360px;
}

.abrir-caja__ayuda {
  margin: -4px 0 4px;
  font-size: 12px;
  line-height: 1.5;
  color: var(--app-ink-2);
}
</style>
