<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Usuarios"
      :subtitle="`${pagination.rowsNumber} usuarios registrados`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('usuarios.store')"
          variant="primary"
          label="Nuevo usuario"
          icon="person_add"
          @click="crear"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por nombre, usuario o email"
      :refreshing="loading"
      @refresh="tableRef.actualizar()"
    />

    <AppTable
      ref="tableRef"
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :filter="filter"
      no-data-label="No hay usuarios que coincidan con la búsqueda."
      @request="onRequest"
    >
      <template #body-cell-id="props">
        <q-td
          :props="props"
          class="text-mono"
        >
          #{{ props.row.id }}
        </q-td>
      </template>

      <template #body-cell-name="props">
        <q-td :props="props">
          <div class="usuario-cell__name">
            {{ props.row.name }}
            <span
              v-if="esYo(props.row)"
              class="usuario-cell__me"
            >Vos</span>
          </div>
          <div class="usuario-cell__username text-mono">
            @{{ props.row.username }}
          </div>
        </q-td>
      </template>

      <template #body-cell-email="props">
        <q-td :props="props">
          <span :class="{ 'usuario-cell__muted': !props.row.email }">{{ props.row.email ?? '—' }}</span>
        </q-td>
      </template>

      <template #body-cell-roles="props">
        <q-td :props="props">
          <span :class="{ 'usuario-cell__muted': !props.row.roles?.length }">{{ nombresRoles(props.row) }}</span>
        </q-td>
      </template>

      <template #body-cell-active="props">
        <q-td :props="props">
          <AppChip
            :status="props.row.active ? 'positive' : 'negative'"
            :label="props.row.active ? 'Activo' : 'Inactivo'"
          />
        </q-td>
      </template>

      <template #body-cell-acciones="props">
        <q-td
          :props="props"
          class="text-right text-no-wrap"
        >
          <q-btn
            v-if="userStore.hasPermission('usuarios.update')"
            flat
            dense
            round
            icon="edit"
            size="sm"
            color="grey-7"
            :aria-label="`Editar a ${props.row.username}`"
            @click="editar(props.row)"
          />
          <q-btn
            v-if="userStore.hasPermission('usuarios.sesiones')"
            flat
            dense
            round
            icon="devices"
            size="sm"
            color="grey-7"
            :aria-label="`Ver sesiones de ${props.row.username}`"
            @click="verSesiones(props.row)"
          />
          <!-- Sobre tu propio usuario no: el backend lo rechaza igual, y
               darte de baja te dejaría afuera del sistema. -->
          <q-btn
            v-if="userStore.hasPermission('usuarios.toggle-active')"
            flat
            dense
            round
            :icon="props.row.active ? 'person_off' : 'person_add_alt'"
            size="sm"
            :color="props.row.active ? 'grey-7' : 'positive'"
            :disable="esYo(props.row)"
            :aria-label="`${props.row.active ? 'Dar de baja' : 'Activar'} a ${props.row.username}`"
            @click="toggleActive(props.row)"
          />
          <q-btn
            v-if="userStore.hasPermission('usuarios.destroy')"
            flat
            dense
            round
            icon="delete_outline"
            size="sm"
            color="negative"
            :disable="esYo(props.row)"
            :aria-label="`Eliminar a ${props.row.username}`"
            @click="eliminar(props.row)"
          />
        </q-td>
      </template>
    </AppTable>

    <AppDialog
      v-model="formDialog"
      :title="title"
      size="lg"
      persistent
    >
      <UsuariosForm
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

    <SesionesUsuarioDialog
      v-model="sesionesDialog"
      :usuario="usuarioSesiones"
    />

    <!-- Una sola confirmación para baja/alta y eliminar: cambia el texto y la
         acción, el diálogo es el mismo. -->
    <AppDialog
      v-model="confirmDialog"
      :title="confirmacion?.titulo ?? ''"
    >
      <p class="confirm-text">
        {{ confirmacion?.mensaje }}
      </p>

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="confirmDialog = false"
        />
        <AppButton
          :variant="confirmacion?.variante ?? 'primary'"
          :label="confirmacion?.etiqueta ?? 'Confirmar'"
          data-test="confirmar"
          :loading="confirmando"
          @click="ejecutarConfirmacion"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTable from '@/components/AppTable.vue'
import UsuarioService from '@/services/UsuarioService'
import { useUserStore } from '@/stores/user-store'
import SesionesUsuarioDialog from './SesionesUsuarioDialog.vue'
import UsuariosForm from './UsuariosForm.vue'

const $q = useQuasar()
const userStore = useUserStore()

const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
  { name: 'name', label: 'Usuario', field: 'name', align: 'left', sortable: true },
  { name: 'email', label: 'Email', field: 'email', align: 'left', sortable: true },
  { name: 'sede', label: 'Sede', field: (row) => row.sede?.nombre ?? '— sin sede —', align: 'left' },
  { name: 'roles', label: 'Roles', field: (row) => row.roles?.length ?? 0, align: 'left' },
  { name: 'active', label: 'Estado', field: 'active', align: 'left', sortable: true },
  { name: 'acciones', label: '', field: 'id', align: 'right' }
]

function esYo (row) {
  return row.id === userStore.id
}

function nombresRoles (row) {
  return row.roles?.length ? row.roles.map((rol) => rol.name).join(', ') : 'Sin rol'
}

// ── Tabla (paginación en el servidor) ──
const tableRef = ref()
const rows = ref([])
const loading = ref(false)
const pagination = ref({ sortBy: 'id', descending: true, page: 1, rowsPerPage: 10, rowsNumber: 0 })

// El buscador escribe en `search`; a la tabla le llega `filter` recién cuando
// se deja de tipear, para no pegarle a la API por cada tecla.
const search = ref('')
const filter = ref('')
let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filter.value = value.trim() }, 400)
})

async function onRequest ({ pagination: requested, filter: term }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  loading.value = true

  try {
    const { data, total = 0 } = await UsuarioService.getData({
      params: { rowsPerPage, page, search: term, order_by: descending ? `-${sortBy}` : sortBy }
    })

    rows.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  tableRef.value.requestServerInteraction()
})

// ── Crear / editar en diálogo ──
const formDialog = ref(false)
const formRef = ref()
const title = ref('')
const editId = ref(null)

function crear () {
  editId.value = null
  title.value = 'Nuevo usuario'
  formDialog.value = true
}

function editar (row) {
  editId.value = row.id
  title.value = 'Editar usuario'
  formDialog.value = true
}

function save () {
  formDialog.value = false
  tableRef.value.requestServerInteraction()
  $q.notify({ type: 'positive', message: 'Usuario guardado.', position: 'top-right', timeout: 1500 })
}

// ── Sesiones ──
const sesionesDialog = ref(false)
const usuarioSesiones = ref(null)

function verSesiones (row) {
  usuarioSesiones.value = row
  sesionesDialog.value = true
}

// ── Confirmaciones (baja/alta y eliminar) ──
const confirmDialog = ref(false)
const confirmacion = ref(null)
const confirmando = ref(false)

function pedirConfirmacion (opciones) {
  confirmacion.value = opciones
  confirmDialog.value = true
}

async function ejecutarConfirmacion () {
  confirmando.value = true
  try {
    await confirmacion.value.accion()
    confirmDialog.value = false
  } finally {
    confirmando.value = false
  }
}

function toggleActive (row) {
  const baja = row.active

  pedirConfirmacion({
    titulo: baja ? 'Dar de baja usuario' : 'Activar usuario',
    mensaje: baja
      ? `${row.name} no va a poder ingresar y se cierran todas sus sesiones abiertas.`
      : `${row.name} va a poder volver a ingresar al sistema.`,
    etiqueta: baja ? 'Dar de baja' : 'Activar',
    variante: baja ? 'destructive' : 'primary',
    accion: async () => {
      const { active } = await UsuarioService.toggleActive(row.id)
      row.active = active
      $q.notify({
        type: active ? 'positive' : 'warning',
        message: active ? 'Usuario activado.' : 'Usuario dado de baja.',
        position: 'top-right',
        timeout: 1500
      })
    }
  })
}

function eliminar (row) {
  pedirConfirmacion({
    titulo: 'Eliminar usuario',
    mensaje: `¿Eliminar a ${row.name} (@${row.username})? Esta acción no se puede deshacer. Si sólo querés quitarle el acceso, dalo de baja.`,
    etiqueta: 'Eliminar',
    variante: 'destructive',
    accion: async () => {
      await UsuarioService.delete(row.id)
      tableRef.value.requestServerInteraction()
      $q.notify({ type: 'positive', message: 'Usuario eliminado.', position: 'top-right', timeout: 1500 })
    }
  })
}
</script>

<style lang="scss" scoped>
.usuario-cell__name {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
}

.usuario-cell__me {
  padding: 1px 7px;
  border-radius: 999px;
  background: var(--app-brand-soft);
  font-size: 10.5px;
  font-weight: 600;
  color: var(--app-brand-soft-ink);
}

.usuario-cell__username {
  font-size: 12px;
  color: var(--app-ink-2);
}

.usuario-cell__muted {
  color: var(--app-ink-2);
}

.confirm-text {
  margin: 0;
  font-size: 14px;
  line-height: 1.55;
  color: var(--app-ink-2);
}
</style>
