<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Categorías"
      :subtitle="`${categorias.length} categorías registradas`"
    >
      <template #actions>
        <AppButton
          v-if="userStore.hasPermission('categorias.store')"
          variant="primary"
          label="Nueva categoría"
          icon="add"
          @click="crear()"
        />
      </template>
    </AppPageHeader>

    <AppFilterBar
      v-model:search="search"
      search-placeholder="Buscar por nombre"
      :refreshing="loading"
      @refresh="cargar"
    />

    <!-- ── Árbol (estilo Nuxt UI Tree) ──
         Se aplana a filas visibles con su nivel: así no hace falta un
         componente recursivo y el teclado recorre una sola lista. -->
    <AppCard class="cat-arbol">
      <div class="cat-arbol__tools">
        <button
          type="button"
          class="cat-arbol__tool"
          @click="expandirTodo(true)"
        >
          <q-icon
            name="unfold_more"
            size="16px"
          />
          Expandir todo
        </button>
        <button
          type="button"
          class="cat-arbol__tool"
          @click="expandirTodo(false)"
        >
          <q-icon
            name="unfold_less"
            size="16px"
          />
          Contraer todo
        </button>
      </div>

      <div
        v-if="loading && !categorias.length"
        class="cat-arbol__vacio"
      >
        <q-spinner size="24px" />
      </div>

      <p
        v-else-if="!filas.length"
        class="cat-arbol__vacio"
      >
        {{ filter ? 'No hay categorías que coincidan con la búsqueda.' : 'Todavía no hay categorías.' }}
      </p>

      <ul
        v-else
        class="cat-arbol__lista"
        role="tree"
        aria-label="Categorías"
      >
        <li
          v-for="fila in filas"
          :key="fila.id"
          role="treeitem"
          :aria-level="fila.nivel + 1"
          :aria-expanded="fila.hijos.length ? String(abierta(fila)) : undefined"
          :class="['cat-nodo', { 'cat-nodo--coincide': fila.coincide }]"
          :style="{ '--nivel': fila.nivel }"
        >
          <!-- Guías verticales: una por cada nivel de profundidad. -->
          <span
            v-for="n in fila.nivel"
            :key="n"
            class="cat-nodo__guia"
            :style="{ '--n': n - 1 }"
          />

          <button
            v-if="fila.hijos.length"
            type="button"
            class="cat-nodo__toggle"
            :aria-label="`${abierta(fila) ? 'Contraer' : 'Expandir'} ${fila.nombre}`"
            @click="alternar(fila)"
          >
            <q-icon
              name="chevron_right"
              size="18px"
              :class="['cat-nodo__chevron', { 'cat-nodo__chevron--abierto': abierta(fila) }]"
            />
          </button>
          <span
            v-else
            class="cat-nodo__toggle cat-nodo__toggle--hoja"
          />

          <q-icon
            :name="fila.hijos.length ? (abierta(fila) ? 'folder_open' : 'folder') : 'label'"
            size="18px"
            class="cat-nodo__icono"
          />

          <span
            class="cat-nodo__nombre"
            @click="fila.hijos.length && alternar(fila)"
          >{{ fila.nombre }}</span>

          <span class="cat-nodo__meta">
            <span v-if="fila.productos_count">{{ fila.productos_count }} {{ fila.productos_count === 1 ? 'producto' : 'productos' }}</span>
            <span v-if="fila.hijos.length">{{ fila.hijos.length }} {{ fila.hijos.length === 1 ? 'subcategoría' : 'subcategorías' }}</span>
          </span>

          <span class="cat-nodo__acciones">
            <q-btn
              v-if="userStore.hasPermission('categorias.store')"
              flat
              dense
              round
              icon="add"
              size="sm"
              color="grey-7"
              :aria-label="`Nueva subcategoría de ${fila.nombre}`"
              @click="crear(fila)"
            >
              <q-tooltip>Nueva subcategoría</q-tooltip>
            </q-btn>
            <q-btn
              v-if="userStore.hasPermission('categorias.update')"
              flat
              dense
              round
              icon="edit"
              size="sm"
              color="grey-7"
              :aria-label="`Editar ${fila.nombre}`"
              @click="editar(fila)"
            />
            <!-- Con subcategorías o productos no se puede borrar (el backend
                 responde 409): se deshabilita en vez de dejar que falle. -->
            <q-btn
              v-if="userStore.hasPermission('categorias.destroy')"
              flat
              dense
              round
              icon="delete_outline"
              size="sm"
              color="negative"
              :disable="fila.hijos.length > 0 || fila.productos_count > 0"
              :aria-label="`Eliminar ${fila.nombre}`"
              @click="eliminar(fila)"
            >
              <q-tooltip v-if="fila.hijos.length > 0">
                Tiene subcategorías: elimínalas o muévelas primero
              </q-tooltip>
              <q-tooltip v-else-if="fila.productos_count > 0">
                Tiene productos: muévelos a otra categoría primero
              </q-tooltip>
            </q-btn>
          </span>
        </li>
      </ul>
    </AppCard>

    <AppDialog
      v-model="formDialog"
      :title="title"
      persistent
    >
      <CategoriasForm
        ref="formRef"
        :key="`${editId ?? 'nueva'}-${parentId ?? ''}`"
        :id="editId"
        :parent-id="parentId"
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
      title="Eliminar categoría"
    >
      <p class="confirm-text">
        ¿Eliminar la categoría <strong>{{ aEliminar?.nombre }}</strong>? Esta acción no se puede deshacer.
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
          data-test="confirmar-eliminar"
          :loading="eliminando"
          @click="confirmarEliminar"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterBar from '@/components/AppFilterBar.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import CategoriaService from '@/services/CategoriaService'
import { useUserStore } from '@/stores/user-store'
import CategoriasForm from './CategoriasForm.vue'

const $q = useQuasar()
const userStore = useUserStore()

// ── Datos: todas de una vez (son pocas) y el árbol se arma acá ──
const categorias = ref([])
const loading = ref(false)

async function cargar () {
  loading.value = true
  try {
    const { data } = await CategoriaService.getData({ params: { rowsPerPage: 0, order_by: 'nombre' } })
    categorias.value = data
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await cargar()
  // De entrada se ven las principales abiertas: el primer nivel de hijos.
  abiertas.value = new Set(raices.value.map((c) => c.id))
})

const nodos = computed(() => {
  const porId = new Map(categorias.value.map((c) => [c.id, { ...c, hijos: [] }]))
  for (const nodo of porId.values()) {
    porId.get(nodo.parent_id)?.hijos.push(nodo)
  }
  return porId
})

// Raíz = sin padre (o con un padre que ya no está en la lista).
const raices = computed(() => [...nodos.value.values()].filter((n) => !nodos.value.has(n.parent_id)))

// ── Abrir / cerrar ──
const abiertas = ref(new Set())

function abierta (fila) {
  return Boolean(filter.value) || abiertas.value.has(fila.id)
}

function alternar (fila) {
  const s = new Set(abiertas.value)
  s.has(fila.id) ? s.delete(fila.id) : s.add(fila.id)
  abiertas.value = s
}

function expandirTodo (abrir) {
  abiertas.value = abrir ? new Set([...nodos.value.values()].filter((n) => n.hijos.length).map((n) => n.id)) : new Set()
}

// ── Búsqueda: deja las que coinciden y el camino hasta ellas ──
const search = ref('')
const filter = ref('')
let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filter.value = value.trim() }, 250)
})

const normalizar = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

function visible (nodo, termino) {
  if (!termino) return true
  return normalizar(nodo.nombre).includes(termino) || nodo.hijos.some((h) => visible(h, termino))
}

// Filas visibles, en orden de árbol, con su nivel de sangría.
const filas = computed(() => {
  const termino = normalizar(filter.value)
  const salida = []
  const ordenar = (lista) => [...lista].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))

  const recorrer = (lista, nivel) => {
    for (const nodo of ordenar(lista)) {
      if (!visible(nodo, termino)) continue
      salida.push({ ...nodo, nivel, coincide: Boolean(termino) && normalizar(nodo.nombre).includes(termino) })
      if (nodo.hijos.length && abierta(nodo)) recorrer(nodo.hijos, nivel + 1)
    }
  }
  recorrer(raices.value, 0)
  return salida
})

// ── Crear / editar en diálogo ──
const formDialog = ref(false)
const formRef = ref()
const title = ref('')
const editId = ref(null)
const parentId = ref(null)

// Con `padre` crea una subcategoría ya ubicada debajo de esa fila.
function crear (padre = null) {
  editId.value = null
  parentId.value = padre?.id ?? null
  title.value = padre ? `Nueva subcategoría de ${padre.nombre}` : 'Nueva categoría'
  formDialog.value = true
}

function editar (row) {
  editId.value = row.id
  parentId.value = null
  title.value = 'Editar categoría'
  formDialog.value = true
}

async function save () {
  formDialog.value = false
  // La nueva subcategoría tiene que verse: se abre su padre.
  if (parentId.value) abiertas.value = new Set([...abiertas.value, parentId.value])
  await cargar()
  $q.notify({ type: 'positive', message: 'Categoría guardada.', position: 'top-right', timeout: 1500 })
}

// ── Eliminar con confirmación ──
const confirmDialog = ref(false)
const aEliminar = ref(null)
const eliminando = ref(false)

function eliminar (row) {
  aEliminar.value = row
  confirmDialog.value = true
}

async function confirmarEliminar () {
  eliminando.value = true
  try {
    await CategoriaService.delete(aEliminar.value.id)
    confirmDialog.value = false
    await cargar()
    $q.notify({ type: 'positive', message: 'Categoría eliminada.', position: 'top-right', timeout: 1500 })
  } catch (error) {
    // 409: alguien le agregó subcategorías o productos después de cargar.
    if (error.response?.status === 409) {
      confirmDialog.value = false
      await cargar()
      $q.notify({ type: 'negative', message: error.response.data.message, position: 'top-right', timeout: 3000 })
    }
  } finally {
    eliminando.value = false
  }
}
</script>

<style lang="scss" scoped>
.cat-arbol {
  padding: 8px;
}

.cat-arbol__tools {
  display: flex;
  justify-content: flex-end;
  gap: 4px;
  padding: 2px 4px 8px;
  border-bottom: 1px solid var(--app-border-subtle);
  margin-bottom: 6px;
}

.cat-arbol__tool {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 9px;
  border: 0;
  border-radius: 6px;
  background: none;
  font: inherit;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    background: var(--app-page);
    color: var(--app-ink);
  }
}

.cat-arbol__vacio {
  display: flex;
  justify-content: center;
  margin: 0;
  padding: 32px 16px;
  font-size: 14px;
  color: var(--app-ink-2);
}

.cat-arbol__lista {
  margin: 0;
  padding: 0;
  list-style: none;
}

// ── Nodo ──
// Cada nivel corre la fila 22px; las guías se dibujan en esa sangría.
.cat-nodo {
  position: relative;
  display: flex;
  align-items: center;
  gap: 6px;
  min-height: 40px;
  padding: 0 6px 0 calc(6px + var(--nivel) * 22px);
  border-radius: 8px;

  &:hover {
    background: var(--app-page);

    .cat-nodo__acciones {
      opacity: 1;
    }
  }
}

.cat-nodo__guia {
  position: absolute;
  top: 0;
  bottom: 0;
  left: calc(6px + var(--n) * 22px + 13px);
  width: 1px;
  background: var(--app-border-control);
}

.cat-nodo__toggle {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 26px;
  height: 26px;
  padding: 0;
  border: 0;
  border-radius: 6px;
  background: none;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    background: var(--app-border-subtle);
    color: var(--app-ink);
  }

  &--hoja {
    cursor: default;

    &:hover {
      background: none;
    }
  }
}

.cat-nodo__chevron {
  transition: transform 0.15s ease;

  &--abierto {
    transform: rotate(90deg);
  }
}

.cat-nodo__icono {
  flex-shrink: 0;
  color: var(--q-primary);
}

.cat-nodo__nombre {
  min-width: 0;
  overflow: hidden;
  font-size: 14px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--app-ink);
  cursor: default;
}

.cat-nodo--coincide .cat-nodo__nombre {
  color: var(--app-brand-soft-ink);
}

.cat-nodo__meta {
  display: flex;
  flex-shrink: 0;
  gap: 10px;
  margin-left: 4px;
  font-size: 12px;
  color: var(--app-ink-2);

  span + span::before {
    content: '·';
    margin-right: 10px;
  }
}

// Las acciones aparecen al pasar el mouse (siempre visibles en táctil).
.cat-nodo__acciones {
  display: flex;
  flex-shrink: 0;
  margin-left: auto;
  opacity: 0;
  transition: opacity 0.15s ease;

  &:focus-within {
    opacity: 1;
  }
}

@media (hover: none) {
  .cat-nodo__acciones {
    opacity: 1;
  }
}

@media (max-width: 599px) {
  .cat-nodo {
    padding-left: calc(4px + var(--nivel) * 16px);
  }

  .cat-nodo__guia {
    left: calc(4px + var(--n) * 16px + 13px);
  }

  .cat-nodo__meta {
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
