<template>
  <form
    class="categoria-form"
    novalidate
    @submit.prevent="submit"
  >
    <AppTextField
      v-model="form.categoria.nombre"
      label="Nombre"
      icon="label_outline"
      placeholder="Polos"
      :error="form.errors[`${PATH}.nombre`]"
      :loading="form.validating"
      autofocus
      @change="form.validate(`${PATH}.nombre`)"
    />

    <!-- Etiqueta fuera del control, igual que AppTextField. -->
    <div class="categoria-form__field">
      <label
        :id="padreLabelId"
        class="categoria-form__label"
      >Categoría padre</label>

      <q-select
        v-model="form.categoria.parent_id"
        :options="opcionesFiltradas"
        :aria-labelledby="padreLabelId"
        :loading="cargando"
        :error="Boolean(form.errors[`${PATH}.parent_id`])"
        :error-message="form.errors[`${PATH}.parent_id`]"
        placeholder="Ninguna (categoría principal)"
        dense
        outlined
        hide-bottom-space
        no-error-icon
        emit-value
        map-options
        clearable
        use-input
        fill-input
        hide-selected
        input-debounce="0"
        class="categoria-form__select"
        @filter="filtrar"
        @update:model-value="validarPadre"
      >
        <template #prepend>
          <q-icon
            name="account_tree"
            class="categoria-form__icon"
          />
        </template>

        <template #no-option>
          <q-item>
            <q-item-section class="text-grey">
              No hay categorías que coincidan.
            </q-item-section>
          </q-item>
        </template>
      </q-select>

      <p class="categoria-form__hint">
        Vacío = categoría principal. Elegí una para crearla como subcategoría.
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
import CategoriaService from '@/services/CategoriaService'
import formCategoria from './FormCategoria'
import { opcionesPadre } from './arbol'

const PATH = 'categoria'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  },
  // Padre sugerido al crear (por ejemplo, "Nueva subcategoría" desde una fila).
  parentId: {
    type: Number,
    default: null
  },
  // Nombre sugerido al crear (lo tipeado en un buscador que no la encontró).
  nombre: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['save'])

const form = props.id
  ? useForm('put', `api/categorias/${props.id}`, formCategoria)
  : useForm('post', 'api/categorias', formCategoria)

const padreLabelId = `categoria-padre-${useId()}`

// ── Catálogo de posibles padres ──
const categorias = ref([])
const cargando = ref(true)
const busqueda = ref('')

const opciones = computed(() => opcionesPadre(categorias.value, props.id))
const opcionesFiltradas = computed(() => {
  const termino = busqueda.value.toLowerCase()
  return termino ? opciones.value.filter((o) => o.label.toLowerCase().includes(termino)) : opciones.value
})

function filtrar (valor, update) {
  update(() => { busqueda.value = valor })
}

// El nombre es único entre hermanos: cambiar de padre puede resolver (o
// generar) el error de nombre repetido.
function validarPadre () {
  form.validate(`${PATH}.parent_id`)
  if (form.categoria.nombre) form.validate(`${PATH}.nombre`)
}

onMounted(async () => {
  const [catalogo, categoria] = await Promise.all([
    CategoriaService.getData({ params: { rowsPerPage: 0, order_by: 'nombre' } }),
    props.id ? CategoriaService.get(props.id) : null
  ])

  categorias.value = catalogo.data
  cargando.value = false

  if (categoria) {
    const { nombre, parent_id: parentId } = categoria
    form.setData({ [PATH]: { nombre, parent_id: parentId } })
  } else if (props.parentId || props.nombre) {
    form.setData({ [PATH]: { ...form.categoria, nombre: props.nombre.trim(), parent_id: props.parentId } })
  }
})

async function submit () {
  try {
    const { data } = await form.submit()
    form.reset()
    // La categoría creada/editada, para quien la quiera dejar elegida.
    emit('save', data)
  } catch {
    // 422: los errores quedan en form.errors y se ven debajo de cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.categoria-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.categoria-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.categoria-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

.categoria-form__hint {
  margin: 0;
  font-size: 12px;
  color: var(--app-ink-2);
}

.categoria-form__icon {
  color: var(--app-ink-2);
}

// Mismo radio, borde y foco que AppTextField.
.categoria-form__select {
  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }

  :deep(.q-field__control):hover:before {
    border-color: var(--app-border-control-hover);
  }

  &.q-field--focused :deep(.q-field__control) {
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }
}
</style>
