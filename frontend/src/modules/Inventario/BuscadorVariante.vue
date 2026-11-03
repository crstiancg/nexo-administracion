<template>
  <q-select
    :model-value="null"
    :options="opciones"
    :loading="buscando"
    :aria-label="label"
    :placeholder="label"
    use-input
    input-debounce="300"
    hide-dropdown-icon
    dense
    outlined
    class="buscador-variante"
    @filter="buscar"
    @update:model-value="elegir"
  >
    <template #prepend>
      <q-icon
        name="search"
        class="buscador-variante__icon"
      />
    </template>

    <template #option="scope">
      <q-item
        v-bind="scope.itemProps"
        :disable="yaElegida(scope.opt)"
      >
        <q-item-section side>
          <span
            class="buscador-variante__swatch"
            :style="{ background: scope.opt.color?.hexadecimal ?? 'transparent' }"
          />
        </q-item-section>
        <q-item-section>
          <q-item-label>{{ scope.opt.producto?.nombre }}</q-item-label>
          <q-item-label caption>
            {{ scope.opt.presentacion }}<template v-if="scope.opt.color"> · {{ scope.opt.color.nombre }}</template> ·
            <span class="text-mono">{{ scope.opt.sku }}</span>
          </q-item-label>
        </q-item-section>
        <q-item-section
          side
          class="text-mono"
        >
          {{ yaElegida(scope.opt) ? 'agregada' : `stock ${scope.opt.stock} ${scope.opt.unidad?.abreviatura ?? ''}` }}
        </q-item-section>
      </q-item>
    </template>

    <template #no-option>
      <q-item>
        <q-item-section class="text-grey">
          {{ termino ? 'Ninguna presentación coincide.' : 'Escribí un SKU o el nombre del producto.' }}
        </q-item-section>
      </q-item>
    </template>
  </q-select>
</template>

<script setup>
import { ref } from 'vue'
import InventarioService from '@/services/InventarioService'

/**
 * Busca variantes en el servidor (SKU o nombre del producto) y emite la
 * elegida. No guarda selección: cada elección agrega una línea y el campo
 * queda listo para la siguiente.
 */
const props = defineProps({
  label: {
    type: String,
    default: 'Agregar presentación: SKU o nombre del producto'
  },
  // Ids ya agregados al documento: se muestran deshabilitados.
  excluir: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['elegir'])

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
    const { data } = await InventarioService.variantes({ params: { search: termino.value, rowsPerPage: 20 } })
    if (busqueda === ultimaBusqueda) update(() => { opciones.value = data })
  } catch {
    abort()
  } finally {
    if (busqueda === ultimaBusqueda) buscando.value = false
  }
}

function yaElegida (variante) {
  return props.excluir.includes(variante.id)
}

function elegir (variante) {
  if (variante && !yaElegida(variante)) emit('elegir', variante)
}
</script>

<style lang="scss" scoped>
.buscador-variante {
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

.buscador-variante__icon {
  color: var(--app-ink-2);
}

.buscador-variante__swatch {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 4px;
}
</style>
