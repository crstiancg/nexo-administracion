<template>
  <form
    class="credito-form"
    novalidate
    @submit.prevent="submit"
  >
    <p class="credito-form__resumen">
      Se financia el saldo de <strong class="text-mono">{{ formatearPrecio(saldo) }}</strong>
      de <strong>{{ cliente || 'el cliente' }}</strong>. Las cuotas se cobran como cualquier pago (entran a la caja)
      y el pedido se puede entregar con saldo.
    </p>

    <div class="credito-form__fila">
      <div class="credito-form__campo">
        <span class="credito-form__label">Frecuencia</span>
        <div
          class="credito-form__opciones"
          role="radiogroup"
          aria-label="Frecuencia"
        >
          <button
            v-for="f in FRECUENCIAS"
            :key="f.value"
            type="button"
            role="radio"
            :aria-checked="String(datos.frecuencia === f.value)"
            :class="['credito-form__opcion', { 'credito-form__opcion--activa': datos.frecuencia === f.value }]"
            @click="datos.frecuencia = f.value"
          >
            {{ f.label }}
          </button>
        </div>
      </div>
      <AppTextField
        v-model="datos.numero_cuotas"
        label="N° de cuotas"
        type="number"
        min="1"
        max="52"
        class="credito-form__corto"
        :error="errores.numero_cuotas"
      />
      <AppTextField
        v-model="datos.primer_vencimiento"
        label="Vence la primera"
        type="date"
        :min="hoy"
        class="credito-form__corto"
        :error="errores.primer_vencimiento"
      />
    </div>

    <!-- Vista previa del cronograma (lo arma el backend igual). -->
    <div
      v-if="cuotas.length"
      class="credito-form__cronograma"
    >
      <div
        v-for="c in cuotas.slice(0, 6)"
        :key="c.numero"
        class="credito-form__cuota"
      >
        <span>Cuota {{ c.numero }} · {{ fechaCorta(c.vence) }}</span>
        <strong class="text-mono">{{ formatearPrecio(c.monto) }}</strong>
      </div>
      <p
        v-if="cuotas.length > 6"
        class="credito-form__mas"
      >
        … y {{ cuotas.length - 6 }} cuotas más.
      </p>
    </div>

    <AppTextField
      v-model="datos.observacion"
      label="Nota (opcional)"
      maxlength="250"
      placeholder="Acordado con el cliente por teléfono…"
    />

    <p
      v-if="errorGeneral"
      class="credito-form__error"
      role="alert"
    >
      {{ errorGeneral }}
    </p>
    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import AppTextField from '@/components/AppTextField.vue'
import { fechaCorta, hoyLocal } from '@/modules/Cotizaciones/constantes'
import CreditoService from '@/services/CreditoService'
import { formatearPrecio } from '@/utils/moneda'

const props = defineProps({
  pedidoId: {
    type: Number,
    required: true
  },
  saldo: {
    type: [Number, String],
    required: true
  },
  cliente: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['save'])

const FRECUENCIAS = [
  { value: 'semanal', label: 'Semanal' },
  { value: 'quincenal', label: 'Quincenal' },
  { value: 'mensual', label: 'Mensual' }
]

const hoy = hoyLocal()
const datos = reactive({
  frecuencia: 'semanal',
  numero_cuotas: '4',
  primer_vencimiento: hoyLocal(7),
  observacion: ''
})

function sumar (iso, frecuencia, veces) {
  const d = new Date(`${iso}T12:00:00`)
  if (frecuencia === 'semanal') d.setDate(d.getDate() + 7 * veces)
  else if (frecuencia === 'quincenal') d.setDate(d.getDate() + 15 * veces)
  else d.setMonth(d.getMonth() + veces)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

// Cuotas iguales; la última absorbe los centavos (como el backend).
const cuotas = computed(() => {
  const n = Math.trunc(Number(datos.numero_cuotas))
  const saldo = Number(props.saldo)
  if (!n || n < 1 || n > 52 || !datos.primer_vencimiento || !(saldo > 0)) return []
  const base = Math.floor((saldo / n) * 100) / 100
  return Array.from({ length: n }, (_, i) => ({
    numero: i + 1,
    vence: sumar(datos.primer_vencimiento, datos.frecuencia, i),
    monto: i === n - 1 ? Math.round((saldo - base * (n - 1)) * 100) / 100 : base
  }))
})

const errores = ref({})
const errorGeneral = ref('')
const procesando = ref(false)

async function submit () {
  procesando.value = true
  errores.value = {}
  errorGeneral.value = ''
  try {
    const credito = await CreditoService.otorgar({
      pedido_id: props.pedidoId,
      frecuencia: datos.frecuencia,
      numero_cuotas: Number(datos.numero_cuotas),
      primer_vencimiento: datos.primer_vencimiento,
      observacion: datos.observacion || null
    })
    emit('save', credito)
  } catch (e) {
    const lista = e.response?.data?.errors ?? {}
    errores.value = Object.fromEntries(Object.entries(lista).map(([k, v]) => [k.replace('credito.', ''), [].concat(v)[0]]))
    errorGeneral.value = errores.value.credito ?? (Object.keys(lista).length ? '' : e.response?.data?.message ?? 'No se pudo dar el crédito.')
  } finally {
    procesando.value = false
  }
}

defineExpose({ submit, procesando })
</script>

<style lang="scss" scoped>
.credito-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.credito-form__resumen {
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--app-brand-soft);
  font-size: 13.5px;
  line-height: 1.5;
  color: var(--app-ink);
}

.credito-form__fila {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px;
}

.credito-form__campo {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.credito-form__label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink);
}

.credito-form__opciones {
  display: inline-flex;
  padding: 3px;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
}

.credito-form__opcion {
  padding: 7px 12px;
  border: 0;
  border-radius: 7px;
  background: none;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &--activa {
    background: var(--app-brand-soft);
    color: var(--app-brand-soft-ink);
  }
}

.credito-form__corto {
  flex: 1 1 130px;
  min-width: 0;
}

.credito-form__cronograma {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 6px;
}

.credito-form__cuota {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding: 7px 10px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 8px;
  font-size: 12.5px;
  color: var(--app-ink-2);

  strong {
    color: var(--app-ink);
  }
}

.credito-form__mas {
  grid-column: 1 / -1;
  margin: 0;
  font-size: 12px;
  color: var(--app-ink-2);
}

.credito-form__error {
  margin: 0;
  font-size: 12.5px;
  color: var(--q-negative);
}
</style>
