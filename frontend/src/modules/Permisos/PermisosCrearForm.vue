<template>
  <form
    class="permiso-nuevo"
    novalidate
    @submit.prevent="submit"
  >
    <p class="permiso-nuevo__intro">
      Cada permiso habilita una ruta de la API. Elegí un recurso completo o
      rutas sueltas; el nombre y la descripción se generan solos.
    </p>

    <div
      v-if="cargando"
      class="permiso-nuevo__empty"
    >
      Buscando rutas sin permiso…
    </div>

    <div
      v-else-if="recursos.length === 0"
      class="permiso-nuevo__empty"
    >
      <q-icon
        name="task_alt"
        size="28px"
      />
      <strong>Todas las rutas ya tienen su permiso.</strong>
      <span>Cuando se agregue un módulo nuevo a la API, sus rutas van a aparecer acá.</span>
    </div>

    <div
      v-else
      class="permiso-nuevo__list"
    >
      <section
        v-for="recurso in recursos"
        :key="recurso.recurso"
        class="permiso-nuevo__recurso"
      >
        <!-- Tildar el recurso = "resource" completo; las rutas de abajo se
             pueden elegir sueltas. -->
        <div class="permiso-nuevo__recursoHead">
          <q-checkbox
            :model-value="estadoRecurso(recurso)"
            dense
            toggle-indeterminate
            data-test="recurso-completo"
            @update:model-value="marcarRecurso(recurso)"
          />
          <span class="permiso-nuevo__recursoName">{{ recurso.nombre }}</span>
          <span class="permiso-nuevo__count">{{ elegidas(recurso) }}/{{ recurso.rutas.length }}</span>
        </div>

        <div
          v-for="ruta in recurso.rutas"
          :key="ruta.name"
          class="permiso-nuevo__ruta"
        >
          <q-checkbox
            v-model="form.permiso.rutas"
            :val="ruta.name"
            dense
            class="permiso-nuevo__box"
          >
            <span class="permiso-nuevo__accion">{{ accion(ruta) }}</span>
            <span class="permiso-nuevo__endpoint">
              <span :class="['permiso-nuevo__metodo', `permiso-nuevo__metodo--${ruta.metodo.toLowerCase()}`]">{{ ruta.metodo }}</span>
              {{ ruta.uri }}
            </span>
          </q-checkbox>
        </div>
      </section>
    </div>

    <div
      v-if="errorRutas"
      class="permiso-nuevo__error"
    >
      {{ errorRutas }}
    </div>

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import PermisoService from '@/services/PermisoService'

const PATH = 'permiso'
const SEPARADOR = ' · '

const emit = defineEmits(['save'])

// Payload que valida StorePermisoRequest: `permiso.rutas` con nombres de ruta
// que el backend ofreció como disponibles.
const form = useForm('post', 'api/permisos', () => ({ [PATH]: { rutas: [] } }))

const recursos = ref([])
const cargando = ref(true)

onMounted(async () => {
  recursos.value = await PermisoService.rutasDisponibles()
  cargando.value = false
})

// El primer error de la lista o de cualquier ruta suelta (`permiso.rutas.3`).
const errorRutas = computed(() => {
  const clave = Object.keys(form.errors).find((k) => k === `${PATH}.rutas` || k.startsWith(`${PATH}.rutas.`))
  return clave ? form.errors[clave] : ''
})

function accion (ruta) {
  return ruta.description.includes(SEPARADOR) ? ruta.description.split(SEPARADOR).slice(1).join(SEPARADOR) : ruta.description
}

function elegidas (recurso) {
  return recurso.rutas.filter((r) => form.permiso.rutas.includes(r.name)).length
}

// true = todas, false = ninguna, null = algunas (checkbox indeterminado).
function estadoRecurso (recurso) {
  const n = elegidas(recurso)
  if (n === 0) return false
  return n === recurso.rutas.length ? true : null
}

function marcarRecurso (recurso) {
  const nombres = recurso.rutas.map((r) => r.name)
  const todas = elegidas(recurso) === nombres.length

  form.permiso.rutas = todas
    ? form.permiso.rutas.filter((n) => !nombres.includes(n))
    : [...new Set([...form.permiso.rutas, ...nombres])]
}

async function submit () {
  try {
    const { data } = await form.submit()
    form.reset()
    emit('save', data.length)
  } catch {
    // 422: el error queda en form.errors y se muestra abajo de la lista.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.permiso-nuevo {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.permiso-nuevo__intro {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.permiso-nuevo__list {
  max-height: 380px;
  overflow-y: auto;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
}

.permiso-nuevo__recurso + .permiso-nuevo__recurso {
  border-top: 1px solid var(--app-border-subtle);
}

.permiso-nuevo__recursoHead {
  position: sticky;
  top: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  background: var(--app-page);
  border-bottom: 1px solid var(--app-border-subtle);
}

.permiso-nuevo__recursoName {
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: var(--app-ink);
}

.permiso-nuevo__count {
  margin-left: auto;
  font-family: $font-mono;
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.permiso-nuevo__ruta {
  padding: 9px 12px 9px 20px;

  & + & {
    border-top: 1px solid var(--app-border-subtle);
  }

  &:hover {
    background: var(--app-page);
  }
}

.permiso-nuevo__box {
  width: 100%;

  :deep(.q-checkbox__label) {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding-left: 10px;
  }
}

.permiso-nuevo__accion {
  font-size: 13.5px;
  color: var(--app-ink);
}

.permiso-nuevo__endpoint {
  display: flex;
  align-items: center;
  gap: 6px;
  font-family: $font-mono;
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.permiso-nuevo__metodo {
  min-width: 48px;
  padding: 1px 5px;
  border-radius: 4px;
  background: var(--app-page);
  font-size: 10.5px;
  font-weight: 600;
  text-align: center;
  color: var(--app-ink);

  // Sólo lo destructivo se marca en color: el resto va neutro.
  &--delete {
    background: var(--app-negative-soft);
    color: var(--q-negative);
  }
}

.permiso-nuevo__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 28px 12px;
  font-size: 13px;
  text-align: center;
  color: var(--app-ink-2);

  strong {
    color: var(--app-ink);
  }
}

.permiso-nuevo__error {
  font-size: 12px;
  color: var(--q-negative);
}
</style>
