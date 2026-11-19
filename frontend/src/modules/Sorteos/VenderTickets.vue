<template>
  <!-- Venta de tickets sueltos: cliente, cantidad y precio a criterio. Es
       una venta (canal Sorteo): entra a la caja abierta de tu sede. -->
  <form
    class="vender"
    novalidate
    @submit.prevent="submit"
  >
    <div class="vender__field">
      <div class="vender__labelRow">
        <label
          :id="`${uid}-cliente`"
          class="vender__label"
        >Cliente <span class="vender__requerido">*</span></label>
        <button
          v-if="userStore.hasPermission('clientes.store')"
          type="button"
          class="vender__link"
          @click="clienteDialog = true"
        >
          + Nuevo cliente
        </button>
      </div>
      <BuscadorCliente
        v-model="cliente"
        :aria-labelledby="`${uid}-cliente`"
      />
      <p
        v-if="errores['venta.cliente_id']"
        class="vender__error"
      >
        {{ errores['venta.cliente_id'] }}
      </p>
    </div>

    <div class="vender__fila">
      <AppTextField
        v-model="datos.cantidad"
        label="Cantidad de tickets"
        icon="confirmation_number"
        type="number"
        min="1"
        max="1000"
        step="1"
        :error="errores['venta.cantidad']"
      />
      <AppTextField
        v-model="datos.precio"
        label="Precio por ticket (S/)"
        icon="sell"
        type="number"
        min="0"
        step="0.10"
        inputmode="decimal"
        hint="A tu criterio. 0 = de cortesía."
        :error="errores['venta.precio']"
      />
    </div>

    <div class="vender__total">
      <span>{{ cantidad }} {{ cantidad === 1 ? 'ticket' : 'tickets' }} × {{ formatearPrecio(precio) }}</span>
      <strong class="text-mono">{{ formatearPrecio(total) }}</strong>
    </div>

    <template v-if="total > 0">
      <div class="vender__metodos">
        <span class="vender__label">Método de pago</span>
        <div class="vender__opciones">
          <button
            v-for="m in METODOS"
            :key="m.value"
            type="button"
            :class="['vender__metodo', { 'vender__metodo--activo': datos.metodo === m.value }]"
            :aria-pressed="datos.metodo === m.value"
            @click="datos.metodo = m.value"
          >
            <q-icon
              :name="m.icon"
              size="18px"
            />
            {{ m.label }}
          </button>
        </div>
        <p
          v-if="errores['venta.metodo']"
          class="vender__error"
        >
          {{ errores['venta.metodo'] }}
        </p>
      </div>

      <div class="vender__fila">
        <AppTextField
          v-if="datos.metodo === 'efectivo'"
          v-model="datos.recibido"
          label="Recibido (opcional)"
          icon="payments"
          type="number"
          min="0"
          step="0.10"
          inputmode="decimal"
          :placeholder="total.toFixed(2)"
          :hint="vuelto > 0 ? `Vuelto: ${formatearPrecio(vuelto)}` : 'Para calcular el vuelto.'"
          :error="errores['venta.recibido']"
        />
        <AppTextField
          v-if="CON_OPERACION.includes(datos.metodo)"
          v-model="datos.referencia"
          label="N° de operación *"
          icon="tag"
          maxlength="40"
          :error="errores['venta.referencia']"
        />
      </div>
    </template>

    <p
      v-if="errores.venta"
      class="vender__error vender__error--general"
      role="alert"
    >
      {{ errores.venta }}
    </p>

    <button
      type="submit"
      hidden
    />

    <AppDialog
      v-model="clienteDialog"
      title="Nuevo cliente"
    >
      <ClientesForm
        v-if="clienteDialog"
        ref="clienteFormRef"
        @save="clienteCreado"
        @existente="clienteCreado"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="clienteDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar cliente"
          @click="clienteFormRef?.submit()"
        />
      </template>
    </AppDialog>
  </form>
</template>

<script setup>
import { computed, reactive, ref, useId } from 'vue'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppTextField from '@/components/AppTextField.vue'
import { CON_OPERACION, METODOS } from '@/modules/Caja/constantes'
import ClientesForm from '@/modules/Clientes/ClientesForm.vue'
import BuscadorCliente from '@/modules/Pedidos/BuscadorCliente.vue'
import SorteoService from '@/services/SorteoService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'

const props = defineProps({
  sorteo: {
    type: Object,
    required: true
  }
})

// `venta`: la venta creada (PedidoResource, lista para imprimir su ticket).
const emit = defineEmits(['vendido'])

const uid = useId()
const userStore = useUserStore()

const cliente = ref(null)
const datos = reactive({
  cantidad: 1,
  // El sugerido del sorteo; se cambia a criterio en cada venta.
  precio: props.sorteo.precio_ticket ? Number(props.sorteo.precio_ticket) : '',
  metodo: 'efectivo',
  recibido: '',
  referencia: ''
})
const errores = ref({})
const enviando = ref(false)

const cantidad = computed(() => Math.max(0, Math.floor(Number(datos.cantidad) || 0)))
const precio = computed(() => Math.max(0, Number(datos.precio) || 0))
const total = computed(() => Math.round(cantidad.value * precio.value * 100) / 100)
const vuelto = computed(() => {
  const recibido = Number(datos.recibido)
  return recibido > total.value ? Math.round((recibido - total.value) * 100) / 100 : 0
})

const clienteDialog = ref(false)
const clienteFormRef = ref()
function clienteCreado (nuevo) {
  clienteDialog.value = false
  if (nuevo) cliente.value = nuevo
}

async function submit () {
  if (enviando.value) return
  enviando.value = true
  errores.value = {}
  try {
    const cobra = total.value > 0
    const venta = await SorteoService.vender(props.sorteo.id, {
      cliente_id: cliente.value?.id ?? null,
      cantidad: cantidad.value,
      precio: precio.value,
      metodo: cobra ? datos.metodo : null,
      recibido: cobra && datos.metodo === 'efectivo' && datos.recibido !== '' ? Number(datos.recibido) : null,
      referencia: cobra && CON_OPERACION.includes(datos.metodo) ? datos.referencia : null
    })
    emit('vendido', venta)
  } catch (e) {
    // 422 / 409: el primer mensaje de cada campo, bajo su campo.
    const lista = e.response?.data?.errors
    if (lista) {
      errores.value = Object.fromEntries(Object.entries(lista).map(([clave, mensajes]) => [clave, mensajes[0]]))
    } else {
      errores.value = { venta: e.response?.data?.message ?? 'No se pudo registrar la venta.' }
    }
  } finally {
    enviando.value = false
  }
}

defineExpose({ submit, enviando })
</script>

<style lang="scss" scoped>
.vender {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.vender__field,
.vender__metodos {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.vender__labelRow {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}

.vender__label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink);
}

.vender__requerido {
  color: var(--q-negative);
}

.vender__link {
  padding: 0;
  border: 0;
  background: none;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-primary-hover);
  cursor: pointer;
}

.vender__fila {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.vender__total {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--app-brand-soft);
  font-size: 13.5px;
  color: var(--app-ink-2);

  strong {
    font-size: 22px;
    font-weight: 700;
    color: var(--app-ink);
  }
}

.vender__opciones {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.vender__metodo {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  background: var(--app-surface);
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &:focus-visible {
    outline: 2px solid var(--q-primary);
    outline-offset: 2px;
  }

  &--activo {
    border-color: var(--q-primary);
    background: var(--app-brand-soft);
    color: var(--app-ink);
  }
}

.vender__error {
  margin: 0;
  font-size: 12px;
  color: var(--q-negative);

  &--general {
    padding: 10px 12px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--q-negative) 10%, transparent);
  }
}
</style>
