<template>
  <form
    class="estado-form"
    novalidate
    @submit.prevent="submit"
  >
    <!-- Así se va a ver en el listado de órdenes. -->
    <div class="estado-form__preview">
      <span class="estado-form__label">Vista previa</span>
      <AppChip
        :status="form.estado.color"
        :label="form.estado.nombre || 'Nombre del estado'"
      />
      <q-icon
        :name="form.estado.icono"
        size="20px"
        class="estado-form__previewIcon"
      />
    </div>

    <AppTextField
      v-model="form.estado.nombre"
      label="Nombre"
      placeholder="Enviado a laboratorio, Esperando aprobación…"
      maxlength="40"
      :error="form.errors[`${PATH}.nombre`]"
      autofocus
      @change="form.validate(`${PATH}.nombre`)"
    />

    <div class="estado-form__field">
      <label
        :for="`${uid}-tipo`"
        class="estado-form__label"
      >Qué hace el sistema con la orden</label>
      <q-select
        :id="`${uid}-tipo`"
        v-model="form.estado.tipo"
        :options="opcionesTipo"
        emit-value
        map-options
        dense
        outlined
        hide-bottom-space
        no-error-icon
        :readonly="deSistema"
        :error="Boolean(form.errors[`${PATH}.tipo`])"
        :error-message="form.errors[`${PATH}.tipo`]"
        class="estado-form__control"
      />
      <p class="estado-form__ayuda">
        <template v-if="deSistema">
          Estado del sistema: se renombra y cambia de ícono o color, pero no de tipo.
        </template>
        {{ TIPOS_ESTADO[form.estado.tipo]?.ayuda }}
      </p>
    </div>

    <div class="estado-form__field">
      <span
        :id="`${uid}-color`"
        class="estado-form__label"
      >Color</span>
      <div
        class="estado-form__colores"
        role="radiogroup"
        :aria-labelledby="`${uid}-color`"
      >
        <button
          v-for="color in COLORES_ESTADO"
          :key="color.value"
          type="button"
          role="radio"
          :aria-checked="form.estado.color === color.value"
          :class="['estado-form__color', { 'estado-form__color--activo': form.estado.color === color.value }]"
          @click="form.estado.color = color.value"
        >
          <AppChip
            :status="color.value"
            :label="color.label"
          />
        </button>
      </div>
    </div>

    <div class="estado-form__field">
      <span
        :id="`${uid}-icono`"
        class="estado-form__label"
      >Ícono</span>
      <div
        class="estado-form__iconos"
        role="radiogroup"
        :aria-labelledby="`${uid}-icono`"
      >
        <button
          v-for="icono in ICONOS"
          :key="icono"
          type="button"
          role="radio"
          :aria-checked="form.estado.icono === icono"
          :aria-label="icono"
          :class="['estado-form__icono', { 'estado-form__icono--activo': form.estado.icono === icono }]"
          @click="form.estado.icono = icono"
        >
          <q-icon
            :name="icono"
            size="20px"
          />
        </button>
      </div>
      <p
        v-if="form.errors[`${PATH}.icono`]"
        class="estado-form__error"
      >
        {{ form.errors[`${PATH}.icono`] }}
      </p>
    </div>

    <div class="estado-form__field">
      <label
        :for="`${uid}-mensaje`"
        class="estado-form__label"
      >Lo que ve el cliente en el seguimiento (QR del ticket)</label>
      <q-input
        :id="`${uid}-mensaje`"
        v-model="form.estado.mensaje_cliente"
        type="textarea"
        autogrow
        maxlength="250"
        placeholder="Su equipo está en un laboratorio especializado."
        dense
        outlined
        hide-bottom-space
        no-error-icon
        :error="Boolean(form.errors[`${PATH}.mensaje_cliente`])"
        :error-message="form.errors[`${PATH}.mensaje_cliente`]"
        class="estado-form__control"
      />
    </div>

    <q-toggle
      v-model="form.estado.notificar"
      label="Avisar al cliente por WhatsApp al pasar a este estado (se puede desmarcar en cada orden)"
      color="primary"
      class="estado-form__toggle"
    />

    <q-toggle
      v-model="form.estado.activo"
      :disable="deSistema"
      label="Activo (se puede elegir al cambiar el estado)"
      color="primary"
      class="estado-form__toggle"
    />

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { computed, onMounted, ref, useId } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppChip from '@/components/AppChip.vue'
import AppTextField from '@/components/AppTextField.vue'
import { COLORES_ESTADO, TIPOS_ESTADO } from '@/modules/OrdenesServicio/constantes'
import EstadoOrdenService from '@/services/EstadoOrdenService'

const PATH = 'estado'

// Íconos del taller (Material Symbols, los mismos que usa todo el sistema).
const ICONOS = [
  'move_to_inbox', 'troubleshoot', 'search', 'hourglass_top', 'pending_actions', 'schedule',
  'build', 'construction', 'handyman', 'memory', 'developer_board', 'science',
  'cleaning_services', 'bolt', 'battery_charging_full', 'settings', 'request_quote', 'support_agent',
  'phone_in_talk', 'local_shipping', 'inventory_2', 'task_alt', 'verified', 'check_circle',
  'report_problem', 'block', 'cancel', 'inventory', 'handshake', 'flag'
]

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])
const uid = useId()

const inicial = () => ({
  estado: { nombre: '', tipo: 'en_proceso', color: 'info', icono: 'build', mensaje_cliente: '', notificar: false, activo: true }
})

const form = props.id
  ? useForm('put', `api/estados-orden/${props.id}`, inicial)
  : useForm('post', 'api/estados-orden', inicial)

const deSistema = ref(false)

// Inicial y Entregado no se crean: hay uno de cada uno.
const opcionesTipo = computed(() => Object.entries(TIPOS_ESTADO)
  .filter(([value]) => deSistema.value || !['inicial', 'entregado'].includes(value))
  .map(([value, { label }]) => ({ value, label })))

onMounted(async () => {
  if (!props.id) return

  const estado = await EstadoOrdenService.get(props.id)
  deSistema.value = estado.de_sistema
  form.setData({
    [PATH]: {
      nombre: estado.nombre,
      tipo: estado.tipo,
      color: estado.color,
      icono: estado.icono,
      mensaje_cliente: estado.mensaje_cliente ?? '',
      notificar: estado.notificar,
      activo: estado.activo
    }
  })
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
.estado-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.estado-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.estado-form__label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink);
}

.estado-form__ayuda {
  margin: 0;
  font-size: 12px;
  line-height: 1.45;
  color: var(--app-ink-2);
}

.estado-form__error {
  margin: 0;
  font-size: 12px;
  color: var(--q-negative);
}

.estado-form__preview {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border: 1px solid var(--app-border-control);
  border-radius: 12px;
  background: var(--app-page);

  .estado-form__label {
    margin-right: auto;
  }
}

.estado-form__previewIcon {
  color: var(--app-ink-2);
}

.estado-form__control {
  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);
  }
}

.estado-form__colores {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.estado-form__color {
  padding: 4px;
  border: 2px solid transparent;
  border-radius: 999px;
  background: none;
  cursor: pointer;

  &--activo {
    border-color: var(--q-primary);
  }

  &:focus-visible {
    outline: 2px solid var(--q-primary);
    outline-offset: 2px;
  }
}

.estado-form__iconos {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
  gap: 6px;
}

.estado-form__icono {
  display: grid;
  place-items: center;
  height: 40px;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  background: var(--app-surface);
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    color: var(--app-ink);
  }

  &--activo {
    border-color: var(--q-primary);
    color: var(--q-primary);
    box-shadow: 0 0 0 1px var(--q-primary);
  }

  &:focus-visible {
    outline: 2px solid var(--q-primary);
    outline-offset: 2px;
  }
}

.estado-form__toggle {
  font-size: 13.5px;
  color: var(--app-ink);
}
</style>
