<template>
  <div class="filtros">
    <div class="filtros__cabecera">
      <span class="filtros__overline">Filtros</span>
      <button
        v-if="hayFiltros"
        type="button"
        class="filtros__limpiar"
        @click="limpiar"
      >
        Limpiar
      </button>
    </div>

    <label class="filtros__switch">
      <span>Sólo con stock</span>
      <q-toggle
        :model-value="model.con_stock"
        dense
        color="primary"
        @update:model-value="cambiar('con_stock', $event)"
      />
    </label>

    <section class="filtros__seccion">
      <h3 class="filtros__titulo">
        <q-icon
          name="category"
          size="14px"
        />Categoría
      </h3>
      <div class="filtros__chips">
        <button
          v-for="c in categorias"
          :key="c.id"
          type="button"
          :class="['filtros__chip', { 'filtros__chip--activo': model.categoria_id === c.id }]"
          :aria-pressed="String(model.categoria_id === c.id)"
          :title="c.label"
          @click="alternar('categoria_id', c.id)"
        >
          {{ c.corto }}
        </button>
      </div>
    </section>

    <section class="filtros__seccion">
      <h3 class="filtros__titulo">
        <q-icon
          name="verified"
          size="14px"
        />Marca
      </h3>
      <div class="filtros__chips">
        <button
          v-for="m in marcas"
          :key="m.id"
          type="button"
          :class="['filtros__chip', { 'filtros__chip--activo': model.marca_id === m.id }]"
          :aria-pressed="String(model.marca_id === m.id)"
          @click="alternar('marca_id', m.id)"
        >
          {{ m.nombre }}
        </button>
      </div>
    </section>

    <section
      v-if="colores.length"
      class="filtros__seccion"
    >
      <h3 class="filtros__titulo">
        <q-icon
          name="palette"
          size="14px"
        />Color
      </h3>
      <div class="filtros__colores">
        <button
          v-for="c in colores"
          :key="c.id"
          type="button"
          :class="['filtros__color', { 'filtros__color--activo': model.color_id === c.id }]"
          :style="{ background: c.hexadecimal }"
          :aria-pressed="String(model.color_id === c.id)"
          :aria-label="c.nombre"
          @click="alternar('color_id', c.id)"
        >
          <q-tooltip>{{ c.nombre }}</q-tooltip>
        </button>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { opcionesPadre } from '@/modules/Categorias/arbol'
import CategoriaService from '@/services/CategoriaService'
import ColorService from '@/services/ColorService'
import MarcaService from '@/services/MarcaService'

/**
 * Sidebar de filtros del catálogo. Un clic filtra; otro clic en el mismo lo
 * saca (como en sistema-botica).
 */
const model = defineModel({ type: Object, required: true })

const categoriasLista = ref([])
const marcas = ref([])
const colores = ref([])

// Chip con el nombre corto; la ruta completa ("Impermeabilizantes › Techos") en el título.
const categorias = computed(() => {
  const opciones = opcionesPadre(categoriasLista.value)
  return opciones.map((o) => ({ id: o.value, label: o.label, corto: o.label.split(' › ').pop() }))
})

const hayFiltros = computed(() =>
  Boolean(model.value.categoria_id || model.value.marca_id || model.value.color_id || !model.value.con_stock))

function cambiar (clave, valor) {
  model.value = { ...model.value, [clave]: valor }
}

function alternar (clave, id) {
  cambiar(clave, model.value[clave] === id ? null : id)
}

function limpiar () {
  model.value = { categoria_id: null, marca_id: null, color_id: null, con_stock: true }
}

onMounted(async () => {
  const todos = { params: { rowsPerPage: 0 } }
  const [c, t, co] = await Promise.all([
    CategoriaService.getData(todos),
    MarcaService.getData({ params: { rowsPerPage: 0, order_by: 'nombre', activo: 1 } }),
    ColorService.getData({ params: { rowsPerPage: 0, order_by: 'nombre' } })
  ])
  categoriasLista.value = c.data
  marcas.value = t.data
  colores.value = co.data
})
</script>

<style lang="scss" scoped>
.filtros {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.filtros__cabecera {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.filtros__overline {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--app-ink-2);
}

.filtros__limpiar {
  padding: 0;
  border: 0;
  background: none;
  font-size: 12px;
  font-weight: 600;
  color: $primary;
  cursor: pointer;
}

.filtros__switch {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  color: var(--app-ink);
  cursor: pointer;
}

.filtros__seccion {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.filtros__titulo {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: var(--app-ink-2);
}

.filtros__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.filtros__chip {
  padding: 5px 10px;
  border: 1px solid var(--app-border-control);
  border-radius: 999px;
  background: var(--app-surface);
  font-size: 12px;
  color: var(--app-ink);
  cursor: pointer;

  &--activo {
    border-color: $primary;
    background: $primary;
    color: #FFFFFF;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
}

.filtros__colores {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.filtros__color {
  width: 26px;
  height: 26px;
  padding: 0;
  border: 1px solid var(--app-border-control);
  border-radius: 50%;
  cursor: pointer;

  &--activo {
    box-shadow: 0 0 0 2px var(--app-surface), 0 0 0 4px $primary;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 3px;
  }
}
</style>
