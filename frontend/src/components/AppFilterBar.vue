<template>
  <div class="app-filter-bar">
    <q-input
      v-model="search"
      dense
      outlined
      :placeholder="searchPlaceholder"
      class="app-filter-bar__search"
    >
      <template #prepend>
        <q-icon name="search" />
      </template>
    </q-input>

    <!-- Acá van los AppFilterPill (u otro filtro) de quien use la barra:
         la cantidad y el tipo de filtros varían por página, la barra sólo
         les da el layout y el buscador. -->
    <slot />

    <div class="app-filter-bar__end">
      <div
        v-if="hasActiveFilters"
        class="app-filter-bar__clear"
        @click="$emit('clear')"
      >
        Limpiar filtros
      </div>

      <!-- Trae de nuevo la data de la BD (lo que cargó otro usuario) sin
           recargar la página con F5. Sólo si la lista escucha @refresh. -->
      <q-btn
        v-if="onRefresh"
        flat
        round
        icon="refresh"
        color="grey-7"
        :loading="refreshing"
        aria-label="Actualizar"
        @click="onRefresh"
      >
        <q-tooltip>Actualizar</q-tooltip>
      </q-btn>
    </div>
  </div>
</template>

<script setup>
defineProps({
  searchPlaceholder: {
    type: String,
    default: 'Buscar…'
  },

  // La calcula quien usa la barra: es quien sabe si además del texto hay
  // otros filtros activos (estado, categoría, lo que venga por el slot).
  hasActiveFilters: {
    type: Boolean,
    default: false
  },

  // Gira el botón ⟳ mientras la lista está pidiendo los datos.
  refreshing: {
    type: Boolean,
    default: false
  },

  // @refresh declarado como prop (y no en defineEmits) para saber si la
  // lista lo escucha y mostrar el botón sólo entonces.
  onRefresh: {
    type: Function,
    default: null
  }
})

defineEmits(['clear'])

const search = defineModel('search', { default: '' })
</script>

<style lang="scss" scoped>
.app-filter-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.app-filter-bar__search {
  width: 264px;

  :deep(.q-field__control) {
    height: 40px;
    border-radius: 9px;
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }
}

.app-filter-bar__end {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 10px;
}

.app-filter-bar__clear {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    color: var(--app-ink);
  }
}

@media (max-width: 599px) {
  .app-filter-bar__search {
    width: 100%;
  }
}
</style>
