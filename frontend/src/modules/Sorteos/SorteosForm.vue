<template>
  <form
    class="sorteo-form"
    novalidate
    @submit.prevent="submit"
  >
    <AppTextField
      v-model="form.sorteo.nombre"
      label="Nombre"
      icon="redeem"
      placeholder="Sorteo de Navidad"
      maxlength="100"
      :error="form.errors[`${PATH}.nombre`]"
      autofocus
      @change="form.validate(`${PATH}.nombre`)"
    />

    <!-- ── Período ── -->
    <div class="sorteo-form__field">
      <span class="sorteo-form__label">Período</span>
      <q-btn-toggle
        v-model="form.sorteo.periodo"
        :options="PERIODOS"
        no-caps
        unelevated
        toggle-color="primary"
        class="sorteo-form__toggle"
        @update:model-value="cambioPeriodo"
      />
    </div>
    <div class="sorteo-form__row">
      <AppTextField
        v-model="form.sorteo.inicia_el"
        label="Empieza"
        type="date"
        class="sorteo-form__grow"
        :error="form.errors[`${PATH}.inicia_el`]"
        @change="form.validate(`${PATH}.inicia_el`)"
      />
      <AppTextField
        v-model="form.sorteo.termina_el"
        label="Termina (día del sorteo)"
        type="date"
        :min="form.sorteo.inicia_el"
        class="sorteo-form__grow"
        hint="Desde este día se puede sortear; hasta entonces entran tickets."
        :error="form.errors[`${PATH}.termina_el`]"
        @change="form.validate(`${PATH}.termina_el`)"
      />
    </div>

    <!-- ── Tickets ── -->
    <p class="sorteo-form__regla">
      <q-icon
        name="confirmation_number"
        size="18px"
      />
      Cada venta u orden de servicio de un cliente registrado le da <strong>1 ticket</strong>, sea cual sea el monto.
      Además puedes vender tickets sueltos desde el sorteo, al precio que elijas.
    </p>
    <AppTextField
      v-model="form.sorteo.precio_ticket"
      label="Precio sugerido por ticket vendido (S/, opcional)"
      icon="sell"
      type="number"
      min="0"
      step="0.10"
      placeholder="Ej.: 5.00"
      hint="Se propone al vender tickets; en cada venta puedes poner otro precio."
      :error="form.errors[`${PATH}.precio_ticket`]"
      @change="form.validate(`${PATH}.precio_ticket`)"
    />

    <q-toggle
      v-model="form.sorteo.un_premio_por_cliente"
      label="Un premio por cliente (quien ya ganó no entra a los siguientes premios)"
      color="primary"
    />

    <!-- ── Sedes ── -->
    <div
      v-if="sedes.length > 1"
      class="sorteo-form__field"
    >
      <span class="sorteo-form__label">Sedes que participan</span>
      <div class="sorteo-form__sedes">
        <q-checkbox
          v-for="sede in sedes"
          :key="sede.id"
          v-model="form.sorteo.sedes"
          :val="sede.id"
          :label="sede.nombre"
          dense
        />
      </div>
      <p class="sorteo-form__hint">
        Ninguna marcada = participan todas.
      </p>
    </div>

    <!-- ── Premios ── -->
    <div class="sorteo-form__field">
      <span class="sorteo-form__label">Premios (en orden: el primero es el 1.° premio)</span>
      <div
        v-for="(premio, i) in form.sorteo.premios"
        :key="i"
        class="sorteo-form__premio"
      >
        <span class="sorteo-form__puesto">{{ i + 1 }}.°</span>
        <q-input
          v-model="premio.descripcion"
          :aria-label="`Premio ${i + 1}`"
          :placeholder="i === 0 ? 'Laptop Lenovo IdeaPad 15' : 'Audífonos gamer'"
          maxlength="150"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          :error="Boolean(form.errors[`${PATH}.premios.${i}.descripcion`])"
          :error-message="form.errors[`${PATH}.premios.${i}.descripcion`]"
          class="sorteo-form__control sorteo-form__grow"
        />
        <q-btn
          flat
          dense
          round
          icon="close"
          size="sm"
          color="grey-7"
          :disable="form.sorteo.premios.length === 1"
          :aria-label="`Quitar el premio ${i + 1}`"
          @click="form.sorteo.premios.splice(i, 1)"
        />
      </div>
      <AppButton
        variant="tertiary"
        icon="add"
        label="Agregar premio"
        class="sorteo-form__agregar"
        @click="form.sorteo.premios.push({ descripcion: '' })"
      />
      <p
        v-if="form.errors[`${PATH}.premios`]"
        class="sorteo-form__error"
      >
        {{ form.errors[`${PATH}.premios`] }}
      </p>
    </div>

    <AppTextField
      v-model="form.sorteo.bases"
      label="Bases y condiciones (opcional)"
      type="textarea"
      autogrow
      maxlength="3000"
      placeholder="Participan clientes registrados con DNI. El ganador tiene 15 días para recoger su premio…"
      :error="form.errors[`${PATH}.bases`]"
    />

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppButton from '@/components/AppButton.vue'
import AppTextField from '@/components/AppTextField.vue'
import SedeService from '@/services/SedeService'
import SorteoService from '@/services/SorteoService'
import { formSorteo, PERIODOS, periodoSugerido } from './constantes'

const PATH = 'sorteo'

const props = defineProps({
  // null = crear; con id = editar (sólo abiertos).
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

const form = props.id
  ? useForm('put', `api/sorteos/${props.id}`, formSorteo)
  : useForm('post', 'api/sorteos', formSorteo)

const sedes = ref([])

// Al elegir mensual o trimestral se proponen las fechas del período en curso.
function cambioPeriodo (periodo) {
  if (periodo === 'otro') return
  Object.assign(form.sorteo, periodoSugerido(periodo))
}

onMounted(async () => {
  const [lista, sorteo] = await Promise.all([
    SedeService.activas().catch(() => []),
    props.id ? SorteoService.get(props.id) : null
  ])
  sedes.value = lista
  if (!sorteo) return

  form.setData({
    sorteo: {
      nombre: sorteo.nombre,
      bases: sorteo.bases ?? '',
      periodo: sorteo.periodo,
      inicia_el: sorteo.inicia_el,
      termina_el: sorteo.termina_el,
      precio_ticket: sorteo.precio_ticket ?? '',
      un_premio_por_cliente: sorteo.un_premio_por_cliente,
      sedes: sorteo.sedes.map((s) => s.id),
      premios: sorteo.premios.map((p) => ({ descripcion: p.descripcion }))
    }
  })
})

async function submit () {
  try {
    const respuesta = await form.submit()
    form.reset()
    emit('save', respuesta?.data)
  } catch {
    // 422: los errores quedan en form.errors y se ven en cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.sorteo-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sorteo-form__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 16px;
}

.sorteo-form__grow {
  flex: 1 1 200px;
  min-width: 0;
}

.sorteo-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.sorteo-form__regla {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--app-brand-soft);
  font-size: 13px;
  line-height: 1.45;
  color: var(--app-ink-2);

  .q-icon {
    margin-top: 1px;
    color: var(--q-primary);
  }

  strong {
    color: var(--app-ink);
  }
}

.sorteo-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

.sorteo-form__hint {
  margin: 0;
  font-size: 12px;
  color: var(--app-ink-2);
}

.sorteo-form__toggle {
  align-self: flex-start;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  overflow: hidden;
}

.sorteo-form__sedes {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 20px;
}

.sorteo-form__premio {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sorteo-form__puesto {
  width: 28px;
  flex-shrink: 0;
  font-weight: 700;
  color: var(--app-ink-2);
  font-variant-numeric: tabular-nums;
}

.sorteo-form__agregar {
  align-self: flex-start;
}

.sorteo-form__control {
  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }

  &.q-field--focused :deep(.q-field__control) {
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--q-primary) 12%, transparent);
  }
}

.sorteo-form__error {
  margin: 0;
  font-size: 12px;
  color: var(--q-negative);
}
</style>
