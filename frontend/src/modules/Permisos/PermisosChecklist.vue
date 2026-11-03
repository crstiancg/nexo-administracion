<template>
  <section class="permisos-check">
    <div class="permisos-check__head">
      <span class="permisos-check__title">{{ titulo }}</span>
      <span class="permisos-check__count">{{ model.length }} de {{ permisos.length }}</span>
    </div>

    <div class="permisos-check__tools">
      <q-input
        v-model="busqueda"
        dense
        outlined
        clearable
        placeholder="Filtrar permisos"
        class="permisos-check__search"
      >
        <template #prepend>
          <q-icon name="search" />
        </template>
      </q-input>

      <button
        type="button"
        class="permisos-check__bulk"
        data-test="marcar-todos"
        :disabled="visibles.length === 0"
        @click="marcar(visibles, !todosVisiblesMarcados)"
      >
        {{ todosVisiblesMarcados ? 'Desmarcar todos' : 'Marcar todos' }}
      </button>
    </div>

    <div
      class="permisos-check__list"
      role="group"
      :aria-label="titulo"
    >
      <div
        v-for="grupo in grupos"
        :key="grupo.clave"
        class="permisos-check__group"
      >
        <div class="permisos-check__groupHead">
          <span class="permisos-check__groupName">{{ grupo.nombre }}</span>
          <span class="permisos-check__groupCount">{{ grupo.marcados }}/{{ grupo.permisos.length }}</span>
          <button
            type="button"
            class="permisos-check__bulk permisos-check__bulk--group"
            data-test="marcar-grupo"
            @click="marcar(grupo.permisos, grupo.marcados < grupo.permisos.length)"
          >
            {{ grupo.marcados < grupo.permisos.length ? 'Todo' : 'Nada' }}
          </button>
        </div>

        <div
          v-for="permiso in grupo.permisos"
          :key="permiso.id"
          class="permisos-check__item"
        >
          <q-checkbox
            v-model="model"
            :val="permiso.id"
            dense
            class="permisos-check__box"
          >
            <span class="permisos-check__desc">{{ accion(permiso) }}</span>
            <span class="permisos-check__name">{{ permiso.name }}</span>
          </q-checkbox>
        </div>
      </div>

      <div
        v-if="cargando"
        class="permisos-check__empty"
      >
        Cargando permisos…
      </div>
      <div
        v-else-if="visibles.length === 0"
        class="permisos-check__empty"
      >
        {{ permisos.length ? 'Ningún permiso coincide con el filtro.' : 'Todavía no hay permisos creados.' }}
      </div>
    </div>

    <div
      v-if="error"
      class="permisos-check__error"
    >
      {{ error }}
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'

/**
 * Lista tildable de permisos con buscador y "marcar todos". La usan el form
 * de roles y el de usuarios (permisos directos); el v-model son los IDs.
 */
const props = defineProps({
  permisos: {
    type: Array,
    required: true
  },

  titulo: {
    type: String,
    default: 'Permisos'
  },

  cargando: {
    type: Boolean,
    default: false
  },

  error: {
    type: String,
    default: ''
  }
})

const model = defineModel({ type: Array, default: () => [] })

const busqueda = ref('')

const visibles = computed(() => {
  const term = (busqueda.value ?? '').trim().toLowerCase()
  if (!term) return props.permisos

  return props.permisos.filter((p) =>
    p.name.toLowerCase().includes(term) || (p.description ?? '').toLowerCase().includes(term)
  )
})

const todosVisiblesMarcados = computed(() =>
  visibles.value.length > 0 && visibles.value.every((p) => model.value.includes(p.id))
)

// Los permisos son nombres de ruta (roles.store) con descripción
// "Roles · Crear": el prefijo de la ruta es el módulo y lo que va después del
// "·" es la acción. Agrupado, 50 permisos se leen como 10 módulos.
const SEPARADOR = ' · '

function modulo (permiso) {
  const [nombre] = (permiso.description ?? '').split(SEPARADOR)
  return permiso.description?.includes(SEPARADOR) ? nombre : permiso.name.split('.')[0]
}

function accion (permiso) {
  const descripcion = permiso.description || permiso.name
  return descripcion.includes(SEPARADOR) ? descripcion.split(SEPARADOR).slice(1).join(SEPARADOR) : descripcion
}

const grupos = computed(() => {
  const porClave = new Map()

  for (const permiso of visibles.value) {
    const clave = permiso.name.split('.')[0]
    if (!porClave.has(clave)) porClave.set(clave, { clave, nombre: modulo(permiso), permisos: [] })
    porClave.get(clave).permisos.push(permiso)
  }

  return [...porClave.values()].map((grupo) => ({
    ...grupo,
    marcados: grupo.permisos.filter((p) => model.value.includes(p.id)).length
  }))
})

// Actúa sólo sobre la lista que se le pasa (lo filtrado, o un módulo): filtrar
// "ventas" y marcar todos no debería tocar lo que ya estaba tildado en otro lado.
function marcar (lista, tildar) {
  const ids = lista.map((p) => p.id)

  model.value = tildar
    ? [...new Set([...model.value, ...ids])]
    : model.value.filter((id) => !ids.includes(id))
}
</script>

<style lang="scss" scoped>
.permisos-check__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 8px;
}

.permisos-check__title {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.permisos-check__count {
  font-family: $font-mono;
  font-size: 12px;
  color: var(--app-ink-2);
}

.permisos-check__tools {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.permisos-check__search {
  flex: 1;
}

.permisos-check__bulk {
  flex-shrink: 0;
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-brand-soft-ink);
  cursor: pointer;

  &:hover:not(:disabled) {
    text-decoration: underline;
  }

  &:disabled {
    color: var(--app-disabled-ink);
    cursor: default;
  }
}

.permisos-check__list {
  max-height: 300px;
  overflow-y: auto;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
}

.permisos-check__group + .permisos-check__group {
  border-top: 1px solid var(--app-border-subtle);
}

// Cabecera del módulo pegada arriba mientras se scrollea su lista.
.permisos-check__groupHead {
  position: sticky;
  top: 0;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: var(--app-page);
  border-bottom: 1px solid var(--app-border-subtle);
}

.permisos-check__groupName {
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: var(--app-ink-2);
}

.permisos-check__groupCount {
  font-family: $font-mono;
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.permisos-check__bulk--group {
  margin-left: auto;
  font-size: 12px;
}

.permisos-check__item {
  padding: 9px 12px 9px 20px;

  & + & {
    border-top: 1px solid var(--app-border-subtle);
  }

  &:hover {
    background: var(--app-page);
  }
}

.permisos-check__box {
  width: 100%;

  :deep(.q-checkbox__label) {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding-left: 10px;
  }
}

.permisos-check__desc {
  font-size: 13.5px;
  color: var(--app-ink);
}

.permisos-check__name {
  font-family: $font-mono;
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.permisos-check__empty {
  padding: 20px 12px;
  font-size: 13px;
  text-align: center;
  color: var(--app-ink-2);
}

.permisos-check__error {
  margin-top: 6px;
  font-size: 12px;
  color: var(--q-negative);
}
</style>
