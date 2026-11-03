<template>
  <form
    class="corregir-form"
    novalidate
    @submit.prevent="submit"
  >
    <p class="corregir-form__resumen">
      Entrada del {{ fecha }} · <strong>+{{ formatearCantidad(movimiento.cantidad) }}</strong>
      {{ movimiento.variante?.presentacion }} en {{ movimiento.sede?.nombre }}
    </p>

    <AppTextField
      v-model="datos.costo_unitario"
      label="Costo unitario (S/)"
      type="number"
      icon="payments"
      placeholder="0.00"
      hint="Lo que costó cada unidad. Con esto se calcula el costo promedio y la ganancia."
      :error="errores['entrada.costo_unitario']"
      min="0"
      step="0.01"
    />

    <AppTextField
      v-model="datos.referencia"
      label="Factura / guía"
      icon="receipt_long"
      placeholder="F001-000123"
      hint="N° del comprobante del proveedor, para ubicar esta entrada después."
      :error="errores['entrada.referencia']"
      maxlength="60"
    />

    <template v-if="datos.lotes.length">
      <div
        v-for="(lote, i) in datos.lotes"
        :key="lote.id"
        class="corregir-form__lote"
      >
        <AppTextField
          v-model="lote.codigo"
          :label="datos.lotes.length > 1 ? `Lote ${i + 1}` : 'Lote'"
          icon="qr_code_2"
          :error="errores[`entrada.lotes.${i}.codigo`]"
          maxlength="40"
        />
        <AppTextField
          v-model="lote.vence_at"
          label="Vencimiento"
          type="date"
          :error="errores[`entrada.lotes.${i}.vence_at`]"
        />
      </div>
      <p class="corregir-form__nota">
        Cambiar el lote lo cambia para todo ese lote en la sede, no sólo para esta entrada.
      </p>
    </template>
    <p
      v-else-if="manejaLotes"
      class="corregir-form__nota"
    >
      Esta entrada se cargó sin lote. Asignáselo desde “Lotes en tu sede”, en la ficha.
    </p>
  </form>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import AppTextField from '@/components/AppTextField.vue'
import InventarioService from '@/services/InventarioService'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'

/**
 * Completar o corregir lo que se olvidó cargar en una entrada: costo,
 * factura y lote. La cantidad no se toca (eso se corrige con otro
 * movimiento). Antes de guardar pide confirmación con lo que va a cambiar.
 */
const props = defineProps({
  movimiento: {
    type: Object,
    required: true
  },
  manejaLotes: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['save'])
const $q = useQuasar()

const original = {
  costo_unitario: props.movimiento.costo_unitario !== null ? String(Number(props.movimiento.costo_unitario)) : '',
  referencia: props.movimiento.referencia ?? '',
  lotes: (props.movimiento.lotes ?? []).map((l) => ({ id: l.id, codigo: l.codigo, vence_at: l.vence_at ?? '' }))
}

const datos = reactive(structuredClone(original))
const errores = ref({})
const procesando = ref(false)

const fecha = computed(() => new Intl.DateTimeFormat('es-PE', { dateStyle: 'short' }).format(new Date(props.movimiento.fecha)))

const fechaCorta = (iso) => (iso ? iso.split('-').reverse().join('/') : 'sin fecha')
const precio = (v) => (v === '' ? 'sin costo' : formatearPrecio(v))
const centavos = (v) => (v === '' || v === null ? '' : Number(v).toFixed(2))

// La confirmación va con html (saltos de línea): lo tipeado se escapa.
const escapar = (texto) => String(texto).replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`)

// Lo que cambia, en palabras, para la confirmación.
function cambios () {
  const lista = []
  if (centavos(datos.costo_unitario) !== centavos(original.costo_unitario)) {
    lista.push(`Costo: ${precio(original.costo_unitario)} → ${precio(datos.costo_unitario)} (recalcula el costo promedio)`)
  }
  if (datos.referencia.trim() !== original.referencia) {
    lista.push(`Factura: ${original.referencia || 'sin factura'} → ${datos.referencia.trim() || 'sin factura'}`)
  }
  datos.lotes.forEach((l, i) => {
    const antes = original.lotes[i]
    if (l.codigo.trim().toUpperCase() !== antes.codigo) lista.push(`Lote: ${antes.codigo} → ${l.codigo.trim().toUpperCase()}`)
    if (l.vence_at !== antes.vence_at) lista.push(`Vencimiento del lote ${antes.codigo}: ${fechaCorta(antes.vence_at)} → ${fechaCorta(l.vence_at)}`)
  })
  return lista
}

function submit () {
  const lista = cambios()
  if (!lista.length) {
    $q.notify({ type: 'info', message: 'No cambiaste nada.', position: 'top-right', timeout: 1500 })
    return
  }

  // Se pregunta con un notify que no se cierra solo: nada se guarda sin el sí.
  $q.notify({
    message: 'Vas a modificar esta entrada',
    caption: `${lista.map((c) => `• ${escapar(c)}`).join('<br>')}`,
    html: true,
    icon: 'help_outline',
    color: 'warning',
    textColor: 'dark',
    position: 'center',
    timeout: 0,
    multiLine: true,
    actions: [
      { label: 'Cancelar', color: 'dark' },
      { label: 'Sí, guardar', color: 'dark', handler: guardar }
    ]
  })
}

async function guardar () {
  procesando.value = true
  errores.value = {}
  try {
    const movimiento = await InventarioService.corregirEntrada(props.movimiento.id, {
      costo_unitario: datos.costo_unitario === '' ? null : datos.costo_unitario,
      referencia: datos.referencia.trim() || null,
      lotes: datos.lotes.map((l) => ({ id: l.id, codigo: l.codigo, vence_at: l.vence_at || null }))
    })
    emit('save', movimiento)
  } catch (e) {
    if (e.response?.status === 422) {
      errores.value = Object.fromEntries(Object.entries(e.response.data.errors ?? {}).map(([k, v]) => [k, v[0]]))
    } else {
      $q.notify({ type: 'negative', message: e.response?.data?.message ?? 'No se pudo guardar.', position: 'top-right' })
    }
  } finally {
    procesando.value = false
  }
}

defineExpose({ submit, procesando })
</script>

<style lang="scss" scoped>
.corregir-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.corregir-form__resumen {
  margin: 0;
  font-size: 13.5px;
  color: var(--app-ink-2);
}

.corregir-form__lote {
  display: grid;
  grid-template-columns: 1fr 180px;
  gap: 12px;
}

.corregir-form__nota {
  margin: 0;
  font-size: 12.5px;
  color: var(--app-ink-2);
}

@media (max-width: 599px) {
  .corregir-form__lote {
    grid-template-columns: 1fr;
  }
}
</style>
