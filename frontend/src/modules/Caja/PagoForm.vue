<template>
  <form
    class="pago-form"
    novalidate
    @submit.prevent="submit"
  >
    <div class="pago-form__resumen">
      <span>{{ devolviendo ? 'Pagado' : 'Saldo' }} de <strong class="text-mono">{{ pedido.codigo }}</strong></span>
      <strong class="text-mono pago-form__saldo">{{ formatearPrecio(tope) }}</strong>
    </div>

    <!-- Método: botones grandes, se usa rápido en mostrador. -->
    <div
      class="pago-form__metodos"
      role="radiogroup"
      :aria-label="devolviendo ? 'Método de la devolución' : 'Método de pago'"
    >
      <button
        v-for="metodo in METODOS"
        :key="metodo.value"
        type="button"
        role="radio"
        :aria-checked="String(form.pago.metodo === metodo.value)"
        :class="['pago-form__metodo', { 'pago-form__metodo--activo': form.pago.metodo === metodo.value }]"
        @click="elegirMetodo(metodo.value)"
      >
        <q-icon
          :name="metodo.icon"
          size="20px"
        />
        {{ metodo.label }}
      </button>
    </div>
    <p
      v-if="form.errors[`${PATH}.metodo`]"
      class="pago-form__error"
    >
      {{ form.errors[`${PATH}.metodo`] }}
    </p>

    <div class="pago-form__row">
      <AppTextField
        v-model="form.pago.monto"
        :label="devolviendo ? 'Monto a devolver' : 'Monto a cobrar'"
        icon="sell"
        type="number"
        min="0.01"
        step="0.01"
        class="pago-form__grow"
        :error="form.errors[`${PATH}.monto`]"
        autofocus
        @change="form.validate(`${PATH}.monto`)"
      />

      <!-- Efectivo: lo que entrega el cliente, para calcular el vuelto. -->
      <AppTextField
        v-if="efectivo && !devolviendo"
        v-model="form.pago.recibido"
        label="Recibido"
        icon="payments"
        type="number"
        min="0"
        step="0.01"
        :placeholder="form.pago.monto"
        class="pago-form__grow"
        :error="form.errors[`${PATH}.recibido`]"
      />
    </div>

    <p
      v-if="efectivo && !devolviendo && vuelto !== null"
      :class="['pago-form__vuelto', { 'pago-form__vuelto--falta': vuelto < 0 }]"
      role="status"
    >
      <template v-if="vuelto >= 0">
        Vuelto: <strong class="text-mono">{{ formatearPrecio(vuelto) }}</strong>
      </template>
      <template v-else>
        Faltan <strong class="text-mono">{{ formatearPrecio(-vuelto) }}</strong>
      </template>
    </p>

    <AppTextField
      v-if="!devolviendo && !efectivo"
      v-model="form.pago.referencia"
      :label="pideOperacion ? 'N° de operación' : 'N° de voucher (opcional)'"
      icon="tag"
      maxlength="40"
      :error="form.errors[`${PATH}.referencia`]"
      @change="form.validate(`${PATH}.referencia`)"
    />

    <AppTextField
      v-if="devolviendo"
      v-model="form.pago.motivo"
      label="Motivo"
      icon="notes"
      maxlength="200"
      placeholder="Cliente desistió, cobro duplicado…"
      :error="form.errors[`${PATH}.motivo`]"
      @change="form.validate(`${PATH}.motivo`)"
    />

    <p
      v-if="errorCaja"
      class="pago-form__caja"
      role="alert"
    >
      {{ errorCaja }}
      <router-link
        v-if="userStore.hasPermission('cajas.abrir')"
        to="/pos"
      >
        Abrir en el punto de venta
      </router-link>
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
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import { CON_OPERACION, METODOS } from './constantes'

const PATH = 'pago'

const props = defineProps({
  // { id, codigo, saldo, pagado }
  pedido: {
    type: Object,
    required: true
  },
  modo: {
    type: String,
    default: 'cobrar',
    validator: (valor) => ['cobrar', 'devolver'].includes(valor)
  }
})

const emit = defineEmits(['save'])

const userStore = useUserStore()
const devolviendo = computed(() => props.modo === 'devolver')

// Lo máximo: el saldo al cobrar, lo pagado al devolver.
const tope = computed(() => Number(devolviendo.value ? props.pedido.pagado : props.pedido.saldo))

const form = useForm(
  'post',
  `api/pedidos/${props.pedido.id}/${devolviendo.value ? 'devoluciones' : 'pagos'}`,
  () => ({
    pago: {
      metodo: 'efectivo',
      // Se propone cobrar (o devolver) todo; se corrige si es un adelanto.
      monto: tope.value.toFixed(2),
      ...(devolviendo.value ? { motivo: '' } : { recibido: '', referencia: '' })
    }
  })
)

const efectivo = computed(() => form.pago.metodo === 'efectivo')
const pideOperacion = computed(() => CON_OPERACION.includes(form.pago.metodo))

function elegirMetodo (metodo) {
  form.pago.metodo = metodo
  form.forgetError(`${PATH}.referencia`)
}

// null mientras no se escribió lo recibido.
const vuelto = computed(() => {
  if (form.pago.recibido === '' || form.pago.recibido === null) return null
  return Math.round((Number(form.pago.recibido) - Number(form.pago.monto)) * 100) / 100
})

// 409 del backend: no hay caja abierta (o el pedido cambió de estado).
const errorCaja = ref('')

async function submit () {
  errorCaja.value = ''
  try {
    const respuesta = await form.submit()
    emit('save', respuesta?.data)
  } catch (error) {
    if (error?.response?.status === 409) {
      const data = error.response.data
      errorCaja.value = Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message
    }
    // 422: los errores quedan en form.errors y se ven en cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.pago-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.pago-form__resumen {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--app-border-subtle);
  font-size: 13px;
  color: var(--app-ink-2);
}

.pago-form__saldo {
  font-size: 20px;
  color: var(--app-ink);
}

.pago-form__metodos {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
  gap: 8px;
}

.pago-form__metodo {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 10px 6px;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  background: var(--app-surface);
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    border-color: var(--app-border-control-hover);
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }

  &--activo {
    border-color: $primary;
    background: rgba($primary, 0.08);
    color: $primary;
  }
}

.pago-form__row {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}

.pago-form__grow {
  flex: 1 1 180px;
  min-width: 0;
}

.pago-form__vuelto {
  margin: -6px 0 0;
  font-size: 14px;
  color: var(--app-ink);

  strong {
    font-size: 18px;
  }

  &--falta {
    color: var(--q-negative);
  }
}

.pago-form__error {
  margin: -8px 0 0;
  font-size: 12px;
  color: var(--q-negative);
}

.pago-form__caja {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba($negative, 0.08);
  font-size: 13px;
  color: var(--app-ink);

  a {
    margin-left: 6px;
    font-weight: 600;
    color: $primary;
  }
}
</style>
