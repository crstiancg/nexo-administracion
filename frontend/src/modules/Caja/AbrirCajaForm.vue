<template>
  <form
    class="abrir-caja"
    novalidate
    @submit.prevent="submit"
  >
    <!-- La de ayer se cerró sola a medianoche: lo que quedó en el cajón es
         la apertura de hoy (y el arqueo de esa caja). -->
    <p
      v-if="anterior"
      class="abrir-caja__anterior"
      role="status"
    >
      <q-icon
        name="history"
        size="18px"
      />
      <span>
        La caja del <strong>{{ diaDe(anterior.abierta_at) }}</strong> se cerró sola a medianoche, sin arqueo.
        Según el sistema quedaron <strong>{{ formatearPrecio(anterior.monto_esperado) }}</strong> en efectivo:
        cuéntalo y corrige si hay otra cantidad. Lo que pongas también queda como el arqueo de esa caja.
      </span>
    </p>
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
      Cuenta el sencillo con el que arrancás el día: es la base del arqueo al cerrar.
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
import { onMounted, ref } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppButton from '@/components/AppButton.vue'
import AppTextField from '@/components/AppTextField.vue'
import CajaService from '@/services/CajaService'
import { formatearPrecio } from '@/utils/moneda'

const emit = defineEmits(['save'])

const form = useForm('post', 'api/cajas/abrir', () => ({ caja: { monto_apertura: '' } }))

// 409: otra persona abrió la caja mientras tanto.
const errorCaja = ref('')

// La anterior cerrada sola (sin arqueo): su efectivo esperado se propone.
const anterior = ref(null)
const formatoDia = new Intl.DateTimeFormat('es-PE', { day: '2-digit', month: '2-digit' })
const diaDe = (iso) => (iso ? formatoDia.format(new Date(iso)) : '')

onMounted(async () => {
  try {
    anterior.value = await CajaService.anteriorSinArqueo()
    if (anterior.value && form.caja.monto_apertura === '') {
      form.caja.monto_apertura = String(Number(anterior.value.monto_esperado).toFixed(2))
    }
  } catch {
    // Sin el dato se abre como siempre, desde lo que se escriba.
  }
})

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

.abrir-caja__anterior {
  display: flex;
  gap: 8px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--app-tint-warning);
  color: var(--app-ink-warning);
  font-size: 12.5px;
  line-height: 1.5;

  .q-icon {
    flex: none;
    margin-top: 1px;
  }
}

.abrir-caja__ayuda {
  margin: -4px 0 4px;
  font-size: 12px;
  line-height: 1.5;
  color: var(--app-ink-2);
}
</style>
