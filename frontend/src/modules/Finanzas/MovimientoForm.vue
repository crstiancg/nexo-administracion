<template>
  <form
    class="mov-form"
    novalidate
    @submit.prevent="submit"
  >
    <div class="mov-form__fila">
      <div class="mov-form__campo mov-form__grow">
        <label
          :for="`${uid}-concepto`"
          class="mov-form__label"
        >Concepto <span class="mov-form__req">*</span></label>
        <q-select
          :id="`${uid}-concepto`"
          v-model="datos.concepto_id"
          :options="conceptos"
          option-value="id"
          option-label="nombre"
          emit-value
          map-options
          dense
          outlined
          hide-bottom-space
          :error="Boolean(errores.concepto_id)"
          :error-message="errores.concepto_id"
          class="mov-form__control"
        />
      </div>
      <AppTextField
        v-model="datos.monto"
        label="Monto *"
        icon="sell"
        type="number"
        min="0.01"
        step="0.01"
        class="mov-form__medio"
        :error="errores.monto"
        autofocus
      />
    </div>

    <div
      class="mov-form__metodos"
      role="radiogroup"
      aria-label="Método"
    >
      <button
        v-for="m in METODOS"
        :key="m.value"
        type="button"
        role="radio"
        :aria-checked="String(datos.metodo === m.value)"
        :class="['mov-form__metodo', { 'mov-form__metodo--activo': datos.metodo === m.value }]"
        @click="datos.metodo = m.value"
      >
        <q-icon
          :name="m.icon"
          size="18px"
        />
        {{ m.label }}
      </button>
    </div>

    <!-- Efectivo del cajón: también queda en la caja (y su arqueo cuadra). -->
    <q-toggle
      v-if="datos.metodo === 'efectivo'"
      v-model="datos.de_caja"
      :label="tipo === 'gasto' ? 'Sale del efectivo de la caja abierta' : 'Entra al efectivo de la caja abierta'"
      color="primary"
      class="mov-form__toggle"
    />

    <div class="mov-form__fila">
      <AppTextField
        v-model="datos.fecha"
        label="Fecha *"
        type="date"
        :max="hoy"
        class="mov-form__medio"
        :error="errores.fecha"
      />
      <AppTextField
        v-if="CON_OPERACION.includes(datos.metodo)"
        v-model="datos.referencia"
        label="N° de operación"
        maxlength="40"
        class="mov-form__medio"
        :error="errores.referencia"
      />
    </div>

    <AppTextField
      v-model="datos.descripcion"
      label="Descripción (opcional)"
      :placeholder="tipo === 'gasto' ? 'Recibo de luz de octubre, pago a Juan…' : 'De quién o por qué…'"
      maxlength="250"
      :error="errores.descripcion"
    />

    <p
      v-if="errorGeneral"
      class="mov-form__error"
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
import { computed, onMounted, reactive, ref, useId } from 'vue'
import AppTextField from '@/components/AppTextField.vue'
import { CON_OPERACION, METODOS } from '@/modules/Caja/constantes'
import { hoyLocal } from '@/modules/Cotizaciones/constantes'
import FinanzaService from '@/services/FinanzaService'

const props = defineProps({
  // 'gasto' | 'ingreso': filtra los conceptos.
  tipo: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['save'])

const uid = `mov-${useId()}`
const hoy = hoyLocal()

const datos = reactive({
  concepto_id: null,
  monto: '',
  metodo: 'efectivo',
  de_caja: true,
  fecha: hoy,
  referencia: '',
  descripcion: ''
})

const conceptos = ref([])
onMounted(async () => {
  const { data } = await FinanzaService.conceptos({ params: { tipo: props.tipo, activo: 1, rowsPerPage: 0, order_by: 'nombre' } })
  conceptos.value = data
})

// Errores 422 del backend por campo (movimiento.monto → monto).
const erroresApi = ref({})
const errores = computed(() => Object.fromEntries(
  Object.entries(erroresApi.value).map(([clave, mensajes]) => [clave.replace('movimiento.', ''), [].concat(mensajes)[0]])
))
const errorGeneral = ref('')
const procesando = ref(false)

async function submit () {
  procesando.value = true
  erroresApi.value = {}
  errorGeneral.value = ''
  try {
    const movimiento = await FinanzaService.registrar({
      ...datos,
      de_caja: datos.metodo === 'efectivo' && datos.de_caja,
      referencia: datos.referencia || null,
      descripcion: datos.descripcion || null
    })
    emit('save', movimiento)
  } catch (error) {
    const respuesta = error.response?.data
    if (respuesta?.errors) erroresApi.value = respuesta.errors
    // Sin campo propio (sin caja abierta, por ejemplo).
    const sueltos = Object.entries(respuesta?.errors ?? {}).filter(([clave]) => !clave.startsWith('movimiento.'))
    errorGeneral.value = sueltos.length ? [].concat(sueltos[0][1])[0] : (respuesta?.errors ? '' : respuesta?.message ?? 'No se pudo registrar.')
  } finally {
    procesando.value = false
  }
}

defineExpose({ submit, procesando })
</script>

<style lang="scss" scoped>
.mov-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.mov-form__fila {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 12px;
}

.mov-form__grow {
  flex: 1 1 220px;
  min-width: 0;
}

.mov-form__medio {
  flex: 1 1 150px;
  min-width: 0;
}

.mov-form__campo {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.mov-form__label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink);
}

.mov-form__req {
  color: var(--q-negative);
}

.mov-form__control :deep(.q-field__control) {
  border-radius: 10px;
}

.mov-form__metodos {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 8px;
}

.mov-form__metodo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 40px;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  background: var(--app-surface);
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &--activo {
    border-color: $primary;
    background: var(--app-brand-soft);
    color: var(--app-brand-soft-ink);
  }
}

.mov-form__toggle {
  font-size: 13.5px;
  color: var(--app-ink);
}

.mov-form__error {
  margin: 0;
  font-size: 12.5px;
  color: var(--q-negative);
}
</style>
