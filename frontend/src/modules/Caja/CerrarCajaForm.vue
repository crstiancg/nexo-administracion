<template>
  <form
    class="cerrar-caja"
    novalidate
    @submit.prevent="submit"
  >
    <div class="cerrar-caja__esperado">
      <span>Efectivo esperado según el sistema</span>
      <strong class="text-mono">{{ formatearPrecio(esperado) }}</strong>
    </div>

    <AppTextField
      v-model="form.caja.monto_contado"
      label="Efectivo contado en el cajón"
      icon="payments"
      type="number"
      min="0"
      step="0.01"
      :error="form.errors['caja.monto_contado']"
      autofocus
    />

    <!-- Arqueo en vivo: se ve la diferencia antes de cerrar. -->
    <p
      v-if="diferencia !== null"
      :class="['cerrar-caja__diferencia', claseDiferencia]"
      role="status"
    >
      <template v-if="diferencia === 0">
        Cuadra exacto.
      </template>
      <template v-else-if="diferencia < 0">
        Faltante de <strong class="text-mono">{{ formatearPrecio(-diferencia) }}</strong>.
      </template>
      <template v-else>
        Sobrante de <strong class="text-mono">{{ formatearPrecio(diferencia) }}</strong>.
      </template>
    </p>

    <AppTextField
      v-model="form.caja.observacion"
      :label="diferencia ? 'Explicá la diferencia' : 'Observación (opcional)'"
      type="textarea"
      autogrow
      maxlength="500"
      :error="form.errors['caja.observacion'] || errorObservacion"
    />

    <p class="cerrar-caja__aviso">
      Una caja cerrada no se reabre: lo que se cobre después va a la próxima.
    </p>

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'
import { formatearPrecio } from '@/utils/moneda'

const props = defineProps({
  // CajaResource de la caja abierta (con resumen).
  caja: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['save'])

const form = useForm('post', `api/cajas/${props.caja.id}/cerrar`, () => ({
  caja: { monto_contado: '', observacion: '' }
}))

const esperado = computed(() => Number(props.caja.resumen.efectivo_esperado))

const diferencia = computed(() => {
  if (form.caja.monto_contado === '' || form.caja.monto_contado === null) return null
  return Math.round((Number(form.caja.monto_contado) - esperado.value) * 100) / 100
})

const claseDiferencia = computed(() => {
  if (diferencia.value === 0) return 'cerrar-caja__diferencia--cuadra'
  return diferencia.value < 0 ? 'cerrar-caja__diferencia--faltante' : 'cerrar-caja__diferencia--sobrante'
})

// Una diferencia sin explicación no se cierra: queda para siempre en el
// historial y alguien va a preguntar.
const errorObservacion = ref('')

async function submit () {
  errorObservacion.value = ''
  if (diferencia.value && !form.caja.observacion?.trim()) {
    errorObservacion.value = 'Con diferencia, anotá qué pasó antes de cerrar.'
    return
  }

  try {
    const respuesta = await form.submit()
    emit('save', respuesta?.data)
  } catch {
    // 422/409: los errores quedan en form.errors.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.cerrar-caja {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cerrar-caja__esperado {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--app-border-subtle);
  font-size: 13px;
  color: var(--app-ink-2);

  strong {
    font-size: 20px;
    color: var(--app-ink);
  }
}

.cerrar-caja__diferencia {
  margin: -6px 0 0;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 14px;
  color: var(--app-ink);

  &--cuadra {
    background: rgba($positive, 0.1);
  }

  &--sobrante {
    background: rgba($warning, 0.14);
  }

  &--faltante {
    background: rgba($negative, 0.1);
  }
}

.cerrar-caja__aviso {
  margin: 0;
  font-size: 12px;
  color: var(--app-ink-2);
}
</style>
