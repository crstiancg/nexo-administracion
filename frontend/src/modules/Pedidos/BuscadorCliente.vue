<template>
  <!-- Una sola raíz (display: contents, no cambia el diseño): quien lo usa
       busca el <input> adentro con $el (el POS lo enfoca con un atajo). -->
  <div class="buscador-cliente__raiz">
  <q-select
    :model-value="model"
    :options="opciones"
    :loading="buscando"
    :aria-labelledby="ariaLabelledby"
    placeholder="Cliente varios"
    option-label="nombre"
    use-input
    fill-input
    hide-selected
    input-debounce="300"
    clearable
    dense
    outlined
    hide-bottom-space
    class="buscador-cliente"
    @filter="buscar"
    @update:model-value="model = $event"
  >
    <template #prepend>
      <q-icon
        name="person_search"
        class="buscador-cliente__icon"
      />
    </template>

    <template #option="scope">
      <q-item v-bind="scope.itemProps">
        <q-item-section>
          <q-item-label>{{ scope.opt.nombre }}</q-item-label>
          <q-item-label
            v-if="scope.opt.numero_documento || scope.opt.telefono"
            caption
          >
            <span v-if="scope.opt.numero_documento">{{ scope.opt.tipo_documento }} {{ scope.opt.numero_documento }}</span>
            <span v-if="scope.opt.numero_documento && scope.opt.telefono"> · </span>
            <span v-if="scope.opt.telefono">{{ scope.opt.telefono }}</span>
          </q-item-label>
        </q-item-section>
      </q-item>
    </template>

    <template #no-option>
      <q-item>
        <q-item-section class="text-grey">
          {{ termino ? 'Ningún cliente coincide.' : 'Escribí nombre, documento o teléfono.' }}
        </q-item-section>
      </q-item>
      <!-- No existe: se da de alta ahí mismo, con lo que ya se escribió. -->
      <q-item
        v-if="termino && puedeCrear"
        clickable
        class="buscador-cliente__agregar"
        @click="abrirAlta"
      >
        <q-item-section avatar>
          <q-icon name="person_add" />
        </q-item-section>
        <q-item-section>
          <q-item-label>Agregar “{{ termino }}” como cliente nuevo</q-item-label>
        </q-item-section>
      </q-item>
    </template>
  </q-select>

  <AppDialog
    v-model="altaDialog"
    title="Nuevo cliente"
    persistent
  >
    <ClientesForm
      v-if="altaDialog"
      ref="altaRef"
      :inicial="inicial"
      @save="guardado($event, false)"
      @existente="guardado($event, true)"
    />
    <template #actions>
      <AppButton
        variant="tertiary"
        label="Cancelar"
        @click="altaDialog = false"
      />
      <AppButton
        variant="primary"
        label="Guardar cliente"
        :loading="altaRef?.form.processing"
        @click="altaRef.submit()"
      />
    </template>
  </AppDialog>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import ClientesForm from '@/modules/Clientes/ClientesForm.vue'
import ClienteService from '@/services/ClienteService'
import { useUserStore } from '@/stores/user-store'

/**
 * Elige un cliente buscando en el servidor. El modelo es el cliente completo
 * (para mostrarlo sin otra consulta); vacío = "Cliente varios".
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

// ── Alta rápida ──
const $q = useQuasar()
const userStore = useUserStore()
const puedeCrear = computed(() => userStore.hasPermission('clientes.store'))
const altaDialog = ref(false)
const altaRef = ref()
const inicial = ref(null)

// Lo escrito pasa al formulario: 8 dígitos = DNI, 11 = RUC (se consultan
// solos), otros números = documento a completar, y texto = nombre.
function abrirAlta () {
  const t = termino.value.replace(/\s+/g, '')
  if (/^\d{8}$/.test(t)) inicial.value = { tipo_documento: 'DNI', numero_documento: t }
  else if (/^\d{11}$/.test(t)) inicial.value = { tipo_documento: 'RUC', numero_documento: t }
  else if (/^\d+$/.test(t)) inicial.value = { numero_documento: t }
  else inicial.value = { nombre: termino.value }
  altaDialog.value = true
}

// Recién creado (o el que ya existía con ese documento): queda elegido en
// la venta. También como opción del select, para que se vea en el campo.
function guardado (cliente, yaExistia) {
  altaDialog.value = false
  if (!cliente) return
  opciones.value = [cliente]
  model.value = cliente
  $q.notify({
    type: 'positive',
    message: yaExistia ? `${cliente.nombre} ya estaba registrado: quedó elegido.` : `Cliente ${cliente.nombre} creado y elegido.`,
    position: 'top-right',
    timeout: 2000
  })
}

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
    const { data } = await ClienteService.getData({ params: { search: termino.value, rowsPerPage: 10, order_by: 'nombre' } })
    if (busqueda === ultimaBusqueda) update(() => { opciones.value = data })
  } catch {
    abort()
  } finally {
    if (busqueda === ultimaBusqueda) buscando.value = false
  }
}
</script>

<style lang="scss" scoped>
.buscador-cliente {
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

.buscador-cliente__raiz {
  display: contents;
}

.buscador-cliente__agregar {
  color: $primary;
  font-weight: 600;
}

.buscador-cliente__icon {
  color: var(--app-ink-2);
}
</style>
