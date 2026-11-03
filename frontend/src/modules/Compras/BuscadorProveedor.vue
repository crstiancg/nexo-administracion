<template>
  <q-select
    :model-value="model"
    :options="opciones"
    :loading="buscando"
    :aria-labelledby="ariaLabelledby"
    placeholder="Buscá por razón social o RUC"
    option-label="razon_social"
    use-input
    fill-input
    hide-selected
    input-debounce="300"
    clearable
    dense
    outlined
    hide-bottom-space
    class="buscador-proveedor"
    @filter="buscar"
    @update:model-value="model = $event"
  >
    <template #prepend>
      <q-icon
        name="local_shipping"
        class="buscador-proveedor__icon"
      />
    </template>

    <template #option="scope">
      <q-item v-bind="scope.itemProps">
        <q-item-section>
          <q-item-label>{{ scope.opt.razon_social }}</q-item-label>
          <q-item-label caption>
            RUC {{ scope.opt.ruc }}<template v-if="scope.opt.contacto">
              · {{ scope.opt.contacto }}
            </template>
          </q-item-label>
        </q-item-section>
      </q-item>
    </template>

    <template #no-option>
      <q-item>
        <q-item-section class="text-grey">
          {{ termino ? 'Ningún proveedor activo coincide.' : 'Escribí razón social o RUC.' }}
        </q-item-section>
      </q-item>
    </template>
  </q-select>
</template>

<script setup>
import { ref } from 'vue'
import ProveedorService from '@/services/ProveedorService'

/**
 * Elige un proveedor activo buscando en el servidor. El modelo es el
 * proveedor completo (para mostrarlo sin otra consulta).
 */
defineProps({
  ariaLabelledby: {
    type: String,
    default: undefined
  }
})

const model = defineModel({ type: Object, default: null })

const opciones = ref([])
const buscando = ref(false)
const termino = ref('')

// Las respuestas pueden llegar desordenadas: sólo vale la de la última búsqueda.
let ultimaBusqueda = 0

async function buscar (valor, update, abort) {
  termino.value = valor.trim()
  if (!termino.value) {
    update(() => { opciones.value = [] })
    return
  }

  const busqueda = ++ultimaBusqueda
  buscando.value = true
  try {
    const { data } = await ProveedorService.getData({
      params: { search: termino.value, activo: 1, rowsPerPage: 10, order_by: 'razon_social' }
    })
    if (busqueda === ultimaBusqueda) update(() => { opciones.value = data })
  } catch {
    abort()
  } finally {
    if (busqueda === ultimaBusqueda) buscando.value = false
  }
}
</script>

<style lang="scss" scoped>
.buscador-proveedor {
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

.buscador-proveedor__icon {
  color: var(--app-ink-2);
}
</style>
