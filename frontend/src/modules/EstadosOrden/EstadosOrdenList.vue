<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Estados de orden"
      subtitle="Los pasos del taller, en orden. El tipo decide qué hace el sistema con la orden (entregar, avisos, atrasos)."
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('estados-orden.store')"
          variant="primary"
          label="Nuevo estado"
          icon="add"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <q-inner-loading :showing="loading && !estados.length" />

    <ol
      class="estados"
      aria-label="Estados de la orden, en orden"
    >
      <li
        v-for="(estado, i) in estados"
        :key="estado.id"
        :class="['estados__fila', { 'estados__fila--inactivo': !estado.activo }]"
      >
        <div
          v-if="puedeEditar"
          class="estados__mover"
        >
          <q-btn
            flat
            dense
            round
            size="sm"
            icon="arrow_upward"
            color="grey-7"
            :disable="i === 0 || ordenando"
            :aria-label="`Subir ${estado.nombre}`"
            @click="mover(i, -1)"
          />
          <q-btn
            flat
            dense
            round
            size="sm"
            icon="arrow_downward"
            color="grey-7"
            :disable="i === estados.length - 1 || ordenando"
            :aria-label="`Bajar ${estado.nombre}`"
            @click="mover(i, 1)"
          />
        </div>

        <q-icon
          :name="estado.icono"
          size="22px"
          class="estados__icono"
        />

        <div class="estados__nombre">
          <AppChip
            :status="estado.color"
            :label="estado.nombre"
          />
          <span class="estados__tipo">
            {{ TIPOS_ESTADO[estado.tipo]?.label }}
            <template v-if="estado.de_sistema"> · del sistema</template>
          </span>
          <q-icon
            v-if="estado.notificar"
            name="chat"
            size="16px"
            class="estados__wa"
          >
            <q-tooltip>Avisa al cliente por WhatsApp</q-tooltip>
          </q-icon>
        </div>

        <span class="estados__ordenes text-mono">
          {{ estado.ordenes_count }} {{ estado.ordenes_count === 1 ? 'orden' : 'órdenes' }}
        </span>

        <AppChip
          :status="estado.activo ? 'positive' : 'negative'"
          :label="estado.activo ? 'Activo' : 'Oculto'"
        />

        <div class="estados__acciones">
          <q-btn
            v-if="puedeEditar"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar ${estado.nombre}`"
            @click="editar(estado)"
          />
          <q-btn
            v-if="userStore.hasPermission('estados-orden.destroy') && !estado.de_sistema"
            flat
            dense
            round
            icon="delete_outline"
            size="sm"
            color="negative"
            :aria-label="`Eliminar ${estado.nombre}`"
            @click="eliminar(estado)"
          />
        </div>
      </li>
    </ol>

    <AppDialog
      v-model="formDialog"
      :title="title"
      persistent
    >
      <EstadosOrdenForm
        :id="editId"
        ref="formRef"
        :key="editId ?? 'nuevo'"
        @save="save"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="formDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar"
          :loading="formRef?.form.processing"
          @click="formRef.submit()"
        />
      </template>
    </AppDialog>

    <AppDialog
      v-model="confirmDialog"
      title="Eliminar estado"
    >
      <p class="confirm-text">
        ¿Eliminar el estado <strong>{{ aEliminar?.nombre }}</strong>? Sólo se puede si ninguna orden pasó por él;
        si no, desactívalo.
      </p>

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="confirmDialog = false"
        />
        <AppButton
          variant="destructive"
          label="Eliminar"
          :loading="eliminando"
          @click="confirmarEliminar"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import { TIENDA } from '@/config/tienda'
import { TIPOS_ESTADO } from '@/modules/OrdenesServicio/constantes'
import EstadoOrdenService from '@/services/EstadoOrdenService'
import { useUserStore } from '@/stores/user-store'
import EstadosOrdenForm from './EstadosOrdenForm.vue'

const $q = useQuasar()
const userStore = useUserStore()
const puedeEditar = computed(() => userStore.hasPermission('estados-orden.update'))

const estados = ref([])
const loading = ref(false)

// Lo que muestra todo el sistema (chips, listas, WhatsApp) se actualiza al
// toque, sin volver a iniciar sesión.
function aplicar (lista) {
  estados.value = lista
  TIENDA.estados_orden = lista
}

async function cargar () {
  loading.value = true
  try {
    aplicar(await EstadoOrdenService.getData())
  } finally {
    loading.value = false
  }
}

onMounted(cargar)

// ── Ordenar con las flechas ──
const ordenando = ref(false)

async function mover (i, paso) {
  const lista = [...estados.value]
  ;[lista[i], lista[i + paso]] = [lista[i + paso], lista[i]]
  estados.value = lista
  ordenando.value = true
  try {
    aplicar(await EstadoOrdenService.ordenar(lista.map((e) => e.id)))
  } catch (error) {
    // No quedó guardado: vuelve al orden de la base y se avisa.
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message ?? 'No se pudo guardar el nuevo orden.',
      position: 'top-right',
      timeout: 4000
    })
    await cargar()
  } finally {
    ordenando.value = false
  }
}

// ── Crear / editar en diálogo ──
const formDialog = ref(false)
const formRef = ref()
const title = ref('')
const editId = ref(null)

function crear () {
  editId.value = null
  title.value = 'Nuevo estado'
  formDialog.value = true
}

function editar (estado) {
  editId.value = estado.id
  title.value = `Editar "${estado.nombre}"`
  formDialog.value = true
}

async function save () {
  formDialog.value = false
  await cargar()
  $q.notify({ type: 'positive', message: 'Estado guardado.', position: 'top-right', timeout: 1500 })
}

// ── Eliminar con confirmación ──
const confirmDialog = ref(false)
const aEliminar = ref(null)
const eliminando = ref(false)

function eliminar (estado) {
  aEliminar.value = estado
  confirmDialog.value = true
}

async function confirmarEliminar () {
  eliminando.value = true
  try {
    await EstadoOrdenService.delete(aEliminar.value.id)
    confirmDialog.value = false
    await cargar()
    $q.notify({ type: 'positive', message: 'Estado eliminado.', position: 'top-right', timeout: 1500 })
  } catch (error) {
    // 409: del sistema o ya usado (se desactiva en su lugar).
    if (error.response?.status === 409) {
      confirmDialog.value = false
      $q.notify({ type: 'negative', message: error.response.data.message, position: 'top-right', timeout: 4000 })
    }
  } finally {
    eliminando.value = false
  }
}
</script>

<style lang="scss" scoped>
.estados {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
  border: 1px solid var(--app-border-subtle);
  border-radius: 14px;
  background: var(--app-surface);
  overflow: hidden;
}

.estados__fila {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 10px 16px;

  & + & {
    border-top: 1px solid var(--app-border-subtle);
  }

  &--inactivo .estados__nombre,
  &--inactivo .estados__icono {
    opacity: 0.55;
  }
}

.estados__mover {
  display: flex;
  flex-direction: column;
}

.estados__icono {
  color: var(--app-ink-2);
}

.estados__nombre {
  display: flex;
  flex: 1;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 12px;
  min-width: 0;
}

.estados__tipo {
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.estados__wa {
  color: #25D366;
}

.estados__ordenes {
  font-size: 12.5px;
  color: var(--app-ink-2);
  white-space: nowrap;
}

.estados__acciones {
  display: flex;
  gap: 2px;
}

// Celular: las órdenes se esconden; lo demás entra en una fila.
@media (max-width: 599px) {
  .estados__fila {
    gap: 8px;
    padding: 10px 8px;
  }

  .estados__ordenes {
    display: none;
  }
}

.confirm-text {
  margin: 0;
  font-size: 14px;
  line-height: 1.55;
  color: var(--app-ink-2);

  strong {
    color: var(--app-ink);
  }
}
</style>
