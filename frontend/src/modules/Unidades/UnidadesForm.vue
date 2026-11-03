<template>
  <form
    class="unidad-form"
    novalidate
    @submit.prevent="submit"
  >
    <AppTextField
      v-model="form.unidad.nombre"
      label="Nombre"
      icon="straighten"
      placeholder="Unidad, Caja, Metro…"
      maxlength="40"
      :error="form.errors[`${PATH}.nombre`]"
      :loading="form.validating"
      autofocus
      @change="form.validate(`${PATH}.nombre`)"
    />

    <AppTextField
      v-model="form.unidad.abreviatura"
      label="Abreviatura"
      icon="short_text"
      placeholder="BLS, GL, KG…"
      maxlength="10"
      :error="form.errors[`${PATH}.abreviatura`]"
      @change="form.validate(`${PATH}.abreviatura`)"
    />

    <div class="unidad-form__campo">
      <span class="unidad-form__label">Código SUNAT</span>
      <q-select
        v-model="form.unidad.codigo_sunat"
        :options="CODIGOS_SUNAT"
        use-input
        fill-input
        hide-selected
        new-value-mode="add-unique"
        emit-value
        map-options
        input-debounce="0"
        dense
        outlined
        hide-bottom-space
        :error="Boolean(form.errors[`${PATH}.codigo_sunat`])"
        :error-message="form.errors[`${PATH}.codigo_sunat`]"
        aria-label="Código de unidad SUNAT"
      />
      <p class="unidad-form__hint">
        El que va en las boletas y facturas electrónicas (catálogo 03 de SUNAT). Si no sabes, deja NIU (unidad).
      </p>
    </div>

    <q-toggle
      v-model="form.unidad.fraccionable"
      label="Se vende en fracciones (kg, metros…)"
      color="primary"
      class="unidad-form__toggle"
    />

    <p class="unidad-form__hint">
      La abreviatura sale en tickets y etiquetas. Las unidades fraccionables permiten vender cantidades con decimales.
    </p>

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { onMounted } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'
import UnidadService from '@/services/UnidadService'

const PATH = 'unidad'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

// Función y no objeto: useForm guarda estos datos como "originales" para el reset().
const inicial = () => ({ unidad: { nombre: '', abreviatura: '', codigo_sunat: 'NIU', fraccionable: false } })

// Los más comunes en una tienda de tecnología (catálogo 03 de SUNAT).
const CODIGOS_SUNAT = [
  { value: 'NIU', label: 'NIU · Unidad' },
  { value: 'LTR', label: 'LTR · Litro' },
  { value: 'KGM', label: 'KGM · Kilogramo' },
  { value: 'MTR', label: 'MTR · Metro' },
  { value: 'BX', label: 'BX · Caja' },
  { value: 'PK', label: 'PK · Paquete' },
  { value: 'SET', label: 'SET · Juego' },
  { value: 'RO', label: 'RO · Rollo' },
  { value: 'KT', label: 'KT · Kit' },
  { value: 'ZZ', label: 'ZZ · Servicio' }
]

const form = props.id
  ? useForm('put', `api/unidades/${props.id}`, inicial)
  : useForm('post', 'api/unidades', inicial)

onMounted(async () => {
  if (!props.id) return

  const { nombre, abreviatura, codigo_sunat: codigoSunat, fraccionable } = await UnidadService.get(props.id)
  form.setData({ [PATH]: { nombre, abreviatura, codigo_sunat: codigoSunat ?? 'NIU', fraccionable } })
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
.unidad-form__campo {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.unidad-form__label {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.unidad-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.unidad-form__toggle {
  font-size: 13.5px;
  color: var(--app-ink);
}

.unidad-form__hint {
  margin: -8px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--app-ink-2);
}
</style>
