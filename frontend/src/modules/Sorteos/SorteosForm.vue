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

    <!-- ── Regla de los tickets ── -->
    <div class="sorteo-form__row">
      <AppTextField
        v-model="form.sorteo.monto_por_ticket"
        label="Monto de compra por ticket (S/)"
        icon="confirmation_number"
        type="number"
        min="1"
        step="0.01"
        class="sorteo-form__grow"
        :hint="ejemplo"
        :error="form.errors[`${PATH}.monto_por_ticket`]"
        @change="form.validate(`${PATH}.monto_por_ticket`)"
      />
      <AppTextField
        v-model="form.sorteo.max_tickets_por_compra"
        label="Máximo de tickets por compra (opcional)"
        type="number"
        min="1"
        step="1"
        placeholder="Sin tope"
        class="sorteo-form__grow"
        hint="Para que una compra grande no se lleve todas las chances."
        :error="form.errors[`${PATH}.max_tickets_por_compra`]"
        @change="form.validate(`${PATH}.max_tickets_por_compra`)"
      />
    </div>

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
import { computed, onMounted, ref } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppButton from '@/components/AppButton.vue'
import AppTextField from '@/components/AppTextField.vue'
import SedeService from '@/services/SedeService'
import SorteoService from '@/services/SorteoService'
import { formatearPrecio } from '@/utils/moneda'
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

// "Una compra de S/ 350.00 da 3 tickets."
const ejemplo = computed(() => {
  const monto = Number(form.sorteo.monto_por_ticket)
  if (!(monto > 0)) return 'Cada cuántos soles de compra se da 1 ticket.'
  const compra = monto * 3.5
  let tickets = Math.floor(compra / monto)
  const maximo = Number(form.sorteo.max_tickets_por_compra)
  if (maximo > 0) tickets = Math.min(tickets, maximo)
  return `Ej.: una compra de ${formatearPrecio(compra)} da ${tickets} ${tickets === 1 ? 'ticket' : 'tickets'}.`
})

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
      monto_por_ticket: sorteo.monto_por_ticket,
      max_tickets_por_compra: sorteo.max_tickets_por_compra ?? '',
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
