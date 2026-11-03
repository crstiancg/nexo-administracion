<template>
  <AppDialog
    v-model="model"
    title="Sesiones activas"
  >
    <p class="sesiones__who">
      {{ usuario?.name }} <span class="text-mono">@{{ usuario?.username }}</span>
    </p>

    <div
      v-if="cargando"
      class="sesiones__empty"
    >
      Cargando sesiones…
    </div>

    <div
      v-else-if="sesiones.length === 0"
      class="sesiones__empty"
    >
      <q-icon
        name="verified_user"
        size="28px"
      />
      <strong>Sin sesiones activas</strong>
      <span>Este usuario no tiene ninguna sesión abierta.</span>
    </div>

    <ul
      v-else
      class="sesiones__list"
    >
      <li
        v-for="sesion in sesiones"
        :key="sesion.id"
        class="sesiones__item"
      >
        <q-icon
          name="devices"
          size="20px"
          class="sesiones__icon"
        />

        <div class="sesiones__text">
          <div class="sesiones__start">
            Inició {{ formatFecha(sesion.created_at) }}
            <span class="sesiones__ago">· {{ hace(sesion.created_at) }}</span>
          </div>
          <div class="sesiones__expires">
            Vence {{ formatFecha(sesion.expires_at) }}
          </div>
        </div>

        <q-btn
          v-if="puedeRevocar"
          flat
          dense
          round
          icon="logout"
          size="sm"
          color="negative"
          :loading="revocando === sesion.id"
          :aria-label="`Cerrar sesión ${sesion.id}`"
          @click="revocar(sesion)"
        />
      </li>
    </ul>

    <template
      v-if="puedeRevocar && sesiones.length > 0"
      #actions
    >
      <AppButton
        variant="destructive"
        label="Cerrar todas"
        icon="logout"
        data-test="cerrar-todas"
        :loading="revocandoTodas"
        @click="revocarTodas"
      />
    </template>
  </AppDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import UsuarioService from '@/services/UsuarioService'
import { useUserStore } from '@/stores/user-store'

const props = defineProps({
  usuario: {
    type: Object,
    default: null
  }
})

const model = defineModel({ type: Boolean, default: false })

const $q = useQuasar()

// Ver las sesiones (usuarios.sesiones) y cerrarlas son permisos distintos.
const puedeRevocar = computed(() => useUserStore().hasPermission('usuarios.sesiones.revocar'))

const sesiones = ref([])
const cargando = ref(false)
const revocando = ref(null)
const revocandoTodas = ref(false)

// Se recarga cada vez que se abre: las sesiones cambian solas (vencen, el
// usuario entra desde otro lado) y una lista vieja haría revocar de más.
watch(
  () => [model.value, props.usuario?.id],
  async ([abierto, id]) => {
    if (!abierto || !id) return

    cargando.value = true
    try {
      sesiones.value = await UsuarioService.getSesiones(id)
    } finally {
      cargando.value = false
    }
  },
  { immediate: true }
)

const fechaFormatter = new Intl.DateTimeFormat('es-AR', { dateStyle: 'short', timeStyle: 'short' })

function formatFecha (fecha) {
  return fecha ? fechaFormatter.format(new Date(fecha)) : '—'
}

function hace (fecha) {
  const minutos = Math.max(0, Math.floor((Date.now() - new Date(fecha).getTime()) / 60000))
  if (minutos < 60) return `hace ${minutos} min`
  const horas = Math.floor(minutos / 60)
  if (horas < 24) return `hace ${horas} h`
  return `hace ${Math.floor(horas / 24)} d`
}

async function revocar (sesion) {
  revocando.value = sesion.id
  try {
    await UsuarioService.revocarSesion(props.usuario.id, sesion.id)
    sesiones.value = sesiones.value.filter((s) => s.id !== sesion.id)
    $q.notify({ type: 'warning', message: 'Sesión cerrada.', position: 'top-right', timeout: 1200 })
  } finally {
    revocando.value = null
  }
}

async function revocarTodas () {
  revocandoTodas.value = true
  try {
    await Promise.all(sesiones.value.map((s) => UsuarioService.revocarSesion(props.usuario.id, s.id)))
    sesiones.value = []
    $q.notify({ type: 'warning', message: 'Se cerraron todas las sesiones.', position: 'top-right', timeout: 1500 })
  } finally {
    revocandoTodas.value = false
  }
}
</script>

<style lang="scss" scoped>
.sesiones__who {
  margin: 0 0 14px;
  font-size: 14px;
  font-weight: 600;
  color: var(--app-ink);

  .text-mono {
    font-weight: 400;
    color: var(--app-ink-2);
  }
}

.sesiones__list {
  margin: 0;
  padding: 0;
  list-style: none;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
}

.sesiones__item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;

  & + & {
    border-top: 1px solid var(--app-border-subtle);
  }
}

.sesiones__icon {
  color: var(--app-ink-2);
}

.sesiones__text {
  flex: 1;
  min-width: 0;
}

.sesiones__start {
  font-size: 13.5px;
  color: var(--app-ink);
}

.sesiones__ago,
.sesiones__expires {
  font-size: 12px;
  color: var(--app-ink-2);
}

.sesiones__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 24px 12px;
  font-size: 13px;
  text-align: center;
  color: var(--app-ink-2);

  strong {
    color: var(--app-ink);
  }
}
</style>
