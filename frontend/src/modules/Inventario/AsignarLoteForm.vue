<template>
  <form
    class="asignar-form"
    novalidate
    @submit.prevent="submit"
  >
    <p class="asignar-form__resumen">
      {{ presentacion.presentacion }} tiene <strong>{{ formatearCantidad(sinLote) }}</strong>
      sin lote en {{ sede }}. Poné el lote y el vencimiento que figuran en el envase.
    </p>

    <AppTextField
      v-model="datos.codigo"
      label="Lote"
      icon="qr_code_2"
      placeholder="L-2301"
      hint="Si ya existe un lote con ese código, se suma a ese."
      :error="errores['lote.codigo']"
      maxlength="40"
      autofocus
    />

    <div class="asignar-form__fila">
      <AppTextField
        v-model="datos.vence_at"
        label="Vencimiento"
        type="date"
        :error="errores['lote.vence_at']"
      />
      <AppTextField
        v-model="datos.cantidad"
        label="Cantidad"
        type="number"
        hint="Si son varios lotes, asigná uno y después el resto."
        :error="errores['lote.cantidad']"
        min="0"
      />
    </div>
  </form>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import AppTextField from '@/components/AppTextField.vue'
import InventarioService from '@/services/InventarioService'
import { formatearCantidad } from '@/utils/cantidad'

/**
 * Darle lote al stock que quedó sin lote en la sede (p. ej. el stock inicial
 * cargado antes de activar lotes). No mueve stock: reparte lo que ya hay.
 */
const props = defineProps({
  presentacion: {
    type: Object,
    required: true
  },
  sinLote: {
    type: Number,
    required: true
  },
  sede: {
    type: String,
    default: 'tu sede'
  }
})

const emit = defineEmits(['save'])
const $q = useQuasar()

const datos = reactive({ codigo: '', vence_at: '', cantidad: String(props.sinLote) })
const errores = ref({})
const procesando = ref(false)

const escapar = (texto) => String(texto).replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`)

function submit () {
  if (!datos.codigo.trim()) {
    errores.value = { 'lote.codigo': 'Poné el código del lote.' }
    return
  }

  const vence = datos.vence_at ? datos.vence_at.split('-').reverse().join('/') : 'sin fecha'
  // Se pregunta con un notify que no se cierra solo: nada se guarda sin el sí.
  $q.notify({
    message: 'Vas a asignar un lote',
    caption: `${escapar(formatearCantidad(Number(datos.cantidad)))} de ${escapar(props.presentacion.presentacion)} quedan en el lote <strong>${escapar(datos.codigo.trim().toUpperCase())}</strong> (vence ${vence}).`,
    html: true,
    icon: 'help_outline',
    color: 'warning',
    textColor: 'dark',
    position: 'center',
    timeout: 0,
    multiLine: true,
    actions: [
      { label: 'Cancelar', color: 'dark' },
      { label: 'Sí, asignar', color: 'dark', handler: guardar }
    ]
  })
}

async function guardar () {
  procesando.value = true
  errores.value = {}
  try {
    const lote = await InventarioService.asignarLote({
      variante_id: props.presentacion.id,
      codigo: datos.codigo,
      vence_at: datos.vence_at || null,
      cantidad: datos.cantidad
    })
    emit('save', lote)
  } catch (e) {
    if (e.response?.status === 422) {
      errores.value = Object.fromEntries(Object.entries(e.response.data.errors ?? {}).map(([k, v]) => [k, v[0]]))
    } else {
      $q.notify({ type: 'negative', message: e.response?.data?.message ?? 'No se pudo asignar el lote.', position: 'top-right' })
    }
  } finally {
    procesando.value = false
  }
}

defineExpose({ submit, procesando })
</script>

<style lang="scss" scoped>
.asignar-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.asignar-form__resumen {
  margin: 0;
  font-size: 13.5px;
  color: var(--app-ink-2);
}

.asignar-form__fila {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

@media (max-width: 599px) {
  .asignar-form__fila {
    grid-template-columns: 1fr;
  }
}
</style>
