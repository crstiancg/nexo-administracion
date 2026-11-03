<template>
  <form
    class="cliente-form"
    novalidate
    @submit.prevent="submit"
  >
    <div class="cliente-form__row">
      <div class="cliente-form__field cliente-form__tipo">
        <label
          :id="`${uid}-tipo`"
          class="cliente-form__label"
        >Documento</label>
        <q-select
          v-model="form.cliente.tipo_documento"
          :options="TIPOS_DOCUMENTO"
          :aria-labelledby="`${uid}-tipo`"
          dense
          outlined
          emit-value
          map-options
          hide-bottom-space
          class="cliente-form__control"
          @update:model-value="cambioTipo"
        />
      </div>

      <AppTextField
        v-if="form.cliente.tipo_documento"
        v-model="form.cliente.numero_documento"
        label="Número"
        icon="badge"
        :maxlength="tipoActual?.digitos ?? 12"
        :inputmode="tipoActual?.digitos ? 'numeric' : 'text'"
        class="cliente-form__grow"
        :error="form.errors[`${PATH}.numero_documento`]"
        autofocus
        @update:model-value="numeroCambio"
        @change="form.validate(`${PATH}.numero_documento`)"
      >
        <template
          v-if="consultable"
          #aside
        >
          <button
            type="button"
            class="cliente-form__consultar"
            :disabled="!numeroCompleto || consultando"
            @click="consultar"
          >
            <q-spinner
              v-if="consultando"
              size="12px"
            />
            {{ tipoActual.value === 'RUC' ? 'Consultar SUNAT' : 'Consultar RENIEC' }}
          </button>
        </template>
      </AppTextField>
    </div>

    <p
      v-if="aviso"
      :class="['cliente-form__aviso', `cliente-form__aviso--${aviso.tipo}`]"
      role="status"
    >
      {{ aviso.texto }}
      <button
        v-if="aviso.existente"
        type="button"
        class="cliente-form__usar"
        @click="emit('existente', aviso.existente)"
      >
        Usar este cliente
      </button>
    </p>

    <AppTextField
      v-model="form.cliente.nombre"
      :label="form.cliente.tipo_documento === 'RUC' ? 'Razón social' : 'Nombre completo'"
      icon="person"
      maxlength="150"
      :error="form.errors[`${PATH}.nombre`]"
      :autofocus="!form.cliente.tipo_documento"
      @change="form.validate(`${PATH}.nombre`)"
    />

    <div class="cliente-form__row">
      <AppTextField
        v-model="form.cliente.telefono"
        label="Teléfono (opcional)"
        icon="call"
        type="tel"
        maxlength="20"
        class="cliente-form__grow"
        :error="form.errors[`${PATH}.telefono`]"
        @change="form.validate(`${PATH}.telefono`)"
      />
      <AppTextField
        v-model="form.cliente.email"
        label="Email (opcional)"
        icon="mail"
        type="email"
        maxlength="120"
        class="cliente-form__grow"
        :error="form.errors[`${PATH}.email`]"
        @change="form.validate(`${PATH}.email`)"
      />
    </div>

    <AppTextField
      v-model="form.cliente.direccion"
      label="Dirección (opcional)"
      icon="home"
      maxlength="255"
      :error="form.errors[`${PATH}.direccion`]"
      @change="form.validate(`${PATH}.direccion`)"
    />

    <div class="cliente-form__mayorista">
      <q-toggle
        v-model="form.cliente.mayorista"
        label="Cliente mayorista (empresa)"
        color="primary"
      />
      <p class="cliente-form__ayuda">
        Al venderle (punto de venta, pedidos y cotizaciones) se usa el precio por mayor de cada
        presentación que lo tenga. Las que no lo tienen van a precio normal.
      </p>
    </div>

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { computed, onMounted, ref, useId } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'
import ClienteService from '@/services/ClienteService'
import formCliente, { TIPOS_DOCUMENTO } from './FormCliente'

const PATH = 'cliente'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  },
  // Al crear: lo ya escrito en el buscador ({ nombre } o { tipo_documento, numero_documento }).
  inicial: {
    type: Object,
    default: null
  }
})

// `existente`: el documento ya es de un cliente cargado (desde un pedido,
// se usa ese en vez de duplicarlo).
const emit = defineEmits(['save', 'existente'])

const uid = `cliente-${useId()}`

const form = props.id
  ? useForm('put', `api/clientes/${props.id}`, formCliente)
  : useForm('post', 'api/clientes', formCliente)

const tipoActual = computed(() => TIPOS_DOCUMENTO.find((t) => t.value === form.cliente.tipo_documento))
// Sólo DNI y RUC se consultan (el carné de extranjería no tiene API).
const consultable = computed(() => Boolean(tipoActual.value?.digitos))
const numeroCompleto = computed(() =>
  consultable.value && new RegExp(`^\\d{${tipoActual.value.digitos}}$`).test(form.cliente.numero_documento ?? ''))

// ── Consulta RENIEC / SUNAT ──
const consultando = ref(false)
const aviso = ref(null)
let ultimoConsultado = ''

function cambioTipo () {
  aviso.value = null
  ultimoConsultado = ''
  if (!form.cliente.tipo_documento) form.cliente.numero_documento = ''
}

// Al completar los dígitos se consulta solo (una vez por número).
function numeroCambio () {
  aviso.value = null
  if (numeroCompleto.value && form.cliente.numero_documento !== ultimoConsultado && !props.id) consultar()
}

async function consultar () {
  const { tipo_documento: tipo, numero_documento: numero } = form.cliente
  ultimoConsultado = numero
  consultando.value = true

  try {
    const r = await ClienteService.consultarDocumento(tipo, numero)

    if (r.origen === 'local' && r.cliente.id !== props.id) {
      aviso.value = { tipo: 'info', texto: `Ya está registrado: ${r.cliente.nombre}.`, existente: r.cliente }
    } else if (r.origen === 'api') {
      form.cliente.nombre = r.datos.nombre
      if (r.datos.direccion && !form.cliente.direccion) form.cliente.direccion = r.datos.direccion
      form.validate(`${PATH}.nombre`)
      aviso.value = { tipo: 'ok', texto: `Datos de ${tipo === 'RUC' ? 'SUNAT' : 'RENIEC'} completados. Revisalos antes de guardar.` }
    } else if (r.origen === null) {
      aviso.value = r.consulta_habilitada
        ? { tipo: 'warn', texto: 'No se encontró el documento: completá los datos a mano.' }
        : { tipo: 'warn', texto: 'La consulta de documentos no está configurada: completá los datos a mano.' }
    }
  } catch {
    // Un número mal formado lo marca la validación del campo; la consulta
    // nunca bloquea el alta.
    aviso.value = { tipo: 'warn', texto: 'No se pudo consultar ahora: completá los datos a mano.' }
  } finally {
    consultando.value = false
  }
}

onMounted(async () => {
  if (!props.id) {
    // Viene del buscador: se completa con lo escrito y, si es un DNI o RUC
    // completo, se consulta solo.
    if (props.inicial) {
      Object.assign(form.cliente, props.inicial)
      numeroCambio()
    }
    return
  }

  const cliente = await ClienteService.get(props.id)
  form.setData({
    [PATH]: {
      tipo_documento: cliente.tipo_documento,
      numero_documento: cliente.numero_documento ?? '',
      nombre: cliente.nombre,
      telefono: cliente.telefono ?? '',
      email: cliente.email ?? '',
      direccion: cliente.direccion ?? '',
      mayorista: Boolean(cliente.mayorista)
    }
  })
  ultimoConsultado = cliente.numero_documento ?? ''
})

async function submit () {
  try {
    const respuesta = await form.submit()
    form.reset()
    emit('save', respuesta?.data)
  } catch {
    // 422: los errores quedan en form.errors y se ven debajo de cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.cliente-form__mayorista {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cliente-form__ayuda {
  margin: 0 0 0 8px;
  font-size: 12px;
  line-height: 1.45;
  color: var(--app-ink-2);
}

.cliente-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cliente-form__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 16px;
}

.cliente-form__grow {
  flex: 1 1 200px;
  min-width: 0;
}

.cliente-form__tipo {
  flex: 0 1 190px;
}

.cliente-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.cliente-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

// Mismo radio, borde y foco que AppTextField.
.cliente-form__control {
  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }

  &.q-field--focused :deep(.q-field__control) {
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }
}

.cliente-form__consultar {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 0;
  border: 0;
  background: none;
  font-size: 12px;
  font-weight: 600;
  color: $primary;
  cursor: pointer;

  &:disabled {
    color: var(--app-ink-2);
    cursor: default;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
}

.cliente-form__aviso {
  margin: -6px 0 0;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 12.5px;
  line-height: 1.5;

  &--ok {
    background: rgba($positive, 0.1);
    color: var(--app-ink);
  }

  &--info {
    background: rgba($primary, 0.08);
    color: var(--app-ink);
  }

  &--warn {
    background: rgba($warning, 0.14);
    color: var(--app-ink);
  }
}

.cliente-form__usar {
  margin-left: 6px;
  padding: 0;
  border: 0;
  background: none;
  font-weight: 600;
  text-decoration: underline;
  color: $primary;
  cursor: pointer;
}
</style>
