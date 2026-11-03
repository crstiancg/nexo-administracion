<template>
  <form
    class="movimiento-caja"
    novalidate
    @submit.prevent="submit"
  >
    <p class="movimiento-caja__ayuda">
      {{ tipo === 'egreso'
        ? 'Efectivo que sale del cajón y no es una devolución: retiro para el banco, pago a un proveedor…'
        : 'Efectivo que entra al cajón y no es una venta: más sencillo, reposición…' }}
    </p>
    <AppTextField
      v-model="form.movimiento.monto"
      label="Monto"
      icon="payments"
      type="number"
      min="0.01"
      step="0.01"
      :error="form.errors['movimiento.monto']"
      autofocus
      @change="form.validate('movimiento.monto')"
    />
    <AppTextField
      v-model="form.movimiento.concepto"
      label="Concepto"
      icon="notes"
      maxlength="200"
      :placeholder="tipo === 'egreso' ? 'Pago de bolsas al proveedor' : 'Sencillo adicional'"
      :error="form.errors['movimiento.concepto']"
      @change="form.validate('movimiento.concepto')"
    />
    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'

const props = defineProps({
  tipo: {
    type: String,
    required: true,
    validator: (valor) => ['ingreso', 'egreso'].includes(valor)
  }
})

const emit = defineEmits(['save'])

const form = useForm('post', 'api/cajas/movimientos', () => ({
  movimiento: { tipo: props.tipo, monto: '', concepto: '' }
}))

async function submit () {
  try {
    const respuesta = await form.submit()
    emit('save', respuesta?.data)
  } catch {
    // 422: los errores quedan en form.errors y se ven debajo de cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.movimiento-caja {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.movimiento-caja__ayuda {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink-2);
}
</style>
