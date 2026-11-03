<template>
  <form
    class="oferta-form"
    novalidate
    @submit.prevent="submit"
  >
    <AppTextField
      v-model="form.oferta.nombre"
      label="Nombre"
      icon="sell"
      placeholder="Liquidación de verano"
      maxlength="80"
      :error="form.errors[`${PATH}.nombre`]"
      autofocus
      @change="form.validate(`${PATH}.nombre`)"
    />

    <!-- ── A qué se aplica ── -->
    <div class="oferta-form__field">
      <span class="oferta-form__label">Se aplica a</span>
      <q-btn-toggle
        v-model="form.oferta.alcance"
        :options="[
          { label: 'Productos', value: 'productos', icon: 'checkroom' },
          { label: 'Categorías', value: 'categorias', icon: 'category' }
        ]"
        no-caps
        unelevated
        toggle-color="primary"
        class="oferta-form__toggle"
        @update:model-value="cambioAlcance"
      />
    </div>

    <!-- Productos: uno o muchos, cada uno completo o sólo algunas variantes. -->
    <section
      v-if="form.oferta.alcance === 'productos'"
      class="oferta-form__productos"
    >
      <q-select
        :model-value="null"
        :options="resultados"
        :loading="buscando"
        aria-label="Agregar producto a la oferta"
        placeholder="+ Agregar producto: buscá por nombre"
        option-label="nombre"
        use-input
        input-debounce="300"
        hide-dropdown-icon
        dense
        outlined
        class="oferta-form__control"
        @filter="buscarProductos"
        @update:model-value="agregarProducto"
      >
        <template #prepend>
          <q-icon name="search" />
        </template>
        <template #option="scope">
          <q-item
            v-bind="scope.itemProps"
            :disable="yaEsta(scope.opt.id)"
          >
            <q-item-section>{{ scope.opt.nombre }}</q-item-section>
            <q-item-section
              side
              class="text-mono"
            >
              {{ yaEsta(scope.opt.id) ? 'agregado' : formatearPrecio(scope.opt.precio) }}
            </q-item-section>
          </q-item>
        </template>
        <template #no-option>
          <q-item>
            <q-item-section class="text-grey">
              Escribí para buscar.
            </q-item-section>
          </q-item>
        </template>
      </q-select>

      <p
        v-if="form.errors[`${PATH}.productos`]"
        class="oferta-form__error"
      >
        {{ form.errors[`${PATH}.productos`] }}
      </p>

      <div
        v-for="(fila, i) in filas"
        :key="fila.producto_id"
        class="oferta-producto"
      >
        <div class="oferta-producto__cabecera">
          <div>
            <div class="oferta-producto__nombre">
              {{ fila.nombre }}
            </div>
            <div
              v-if="previaDe(fila)"
              class="oferta-producto__previa"
            >
              <s class="text-mono">{{ formatearPrecio(previaDe(fila).lista) }}</s>
              → <strong class="text-mono">{{ formatearPrecio(previaDe(fila).oferta) }}</strong>
            </div>
          </div>
          <q-btn
            flat
            dense
            round
            size="sm"
            icon="close"
            :aria-label="`Quitar ${fila.nombre} de la oferta`"
            @click="quitarProducto(i)"
          />
        </div>

        <q-btn-toggle
          v-model="fila.soloAlgunas"
          :options="[{ label: 'Todas las variantes', value: false }, { label: 'Sólo algunas', value: true }]"
          no-caps
          dense
          unelevated
          toggle-color="primary"
          size="sm"
          class="oferta-form__toggle"
          @update:model-value="sincronizar"
        />

        <div
          v-if="fila.soloAlgunas"
          class="oferta-producto__variantes"
          role="group"
          :aria-label="`Variantes de ${fila.nombre}`"
        >
          <button
            v-for="v in fila.disponibles"
            :key="v.id"
            type="button"
            :class="['oferta-variante', { 'oferta-variante--activa': fila.variantes.includes(v.id) }]"
            :aria-pressed="String(fila.variantes.includes(v.id))"
            @click="alternarVariante(fila, v.id)"
          >
            <span
              v-if="v.color"
              class="oferta-variante__swatch"
              :style="{ background: v.color.hexadecimal }"
            />
            {{ v.presentacion }}<template v-if="v.color"> · {{ v.color.nombre }}</template>
          </button>
          <p
            v-if="!fila.variantes.length"
            class="oferta-form__error"
          >
            Elegí al menos una variante (o pasá a "Todas").
          </p>
        </div>

        <p
          v-for="mensaje in erroresDe(i)"
          :key="mensaje"
          class="oferta-form__error"
        >
          {{ mensaje }}
        </p>
      </div>
    </section>

    <!-- Categorías: una o varias. -->
    <div
      v-else
      class="oferta-form__field"
    >
      <label
        :id="`${uid}-categorias`"
        class="oferta-form__label"
      >Categorías</label>
      <q-select
        v-model="form.oferta.categorias"
        :options="categorias"
        :aria-labelledby="`${uid}-categorias`"
        multiple
        use-chips
        emit-value
        map-options
        dense
        outlined
        hide-bottom-space
        no-error-icon
        :error="Boolean(form.errors[`${PATH}.categorias`])"
        :error-message="form.errors[`${PATH}.categorias`]"
        class="oferta-form__control"
      />
      <q-toggle
        v-model="form.oferta.incluye_subcategorias"
        label="Incluir sus subcategorías"
        dense
        class="oferta-form__texto"
      />
    </div>

    <!-- ── Descuento ── -->
    <div class="oferta-form__row">
      <div class="oferta-form__field">
        <span class="oferta-form__label">Tipo</span>
        <q-btn-toggle
          v-model="form.oferta.tipo"
          :options="tipos"
          no-caps
          unelevated
          toggle-color="primary"
          class="oferta-form__toggle"
        />
      </div>

      <AppTextField
        v-model="form.oferta.valor"
        :label="form.oferta.tipo === 'porcentaje' ? 'Descuento (%)' : 'Precio de oferta (S/)'"
        :icon="form.oferta.tipo === 'porcentaje' ? 'percent' : 'payments'"
        type="number"
        :min="form.oferta.tipo === 'porcentaje' ? 1 : 0.01"
        :max="form.oferta.tipo === 'porcentaje' ? 90 : undefined"
        step="0.01"
        class="oferta-form__grow"
        :error="form.errors[`${PATH}.valor`] || form.errors[`${PATH}.tipo`]"
        @change="form.validate(`${PATH}.valor`)"
      />
    </div>

    <!-- ── Período (hora de acá; viaja con zona horaria) ── -->
    <div class="oferta-form__row">
      <AppTextField
        v-model="inicio"
        label="Desde"
        icon="event"
        type="datetime-local"
        class="oferta-form__grow"
        :error="form.errors[`${PATH}.inicia_at`]"
      />
      <AppTextField
        v-model="fin"
        label="Hasta"
        icon="event_busy"
        type="datetime-local"
        class="oferta-form__grow"
        :error="form.errors[`${PATH}.termina_at`]"
      />
    </div>

    <q-toggle
      v-model="form.oferta.activa"
      label="Activa (apagala para pausarla sin borrar las fechas)"
      class="oferta-form__texto"
    />

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { computed, onMounted, ref, useId } from 'vue'
import { useQuasar } from 'quasar'
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'
import { opcionesPadre } from '@/modules/Categorias/arbol'
import CategoriaService from '@/services/CategoriaService'
import OfertaService from '@/services/OfertaService'
import ProductoService from '@/services/ProductoService'
import { isoALocal, localAIso } from '@/utils/fechas'
import { formatearPrecio } from '@/utils/moneda'
import formOferta from './FormOferta'

const PATH = 'oferta'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

const $q = useQuasar()
const uid = `oferta-${useId()}`

const form = props.id
  ? useForm('put', `api/ofertas/${props.id}`, formOferta)
  : useForm('post', 'api/ofertas', formOferta)

// Un precio fijo para categorías enteras no tiene sentido (valen distinto).
const tipos = computed(() => [
  { label: '% descuento', value: 'porcentaje' },
  { label: 'Precio fijo', value: 'precio_fijo', disable: form.oferta.alcance === 'categorias' }
])

function cambioAlcance (alcance) {
  if (alcance === 'categorias') form.oferta.tipo = 'porcentaje'
}

// ── Productos de la oferta ──
// Cada fila: { producto_id, nombre, precio, disponibles, soloAlgunas, variantes: [ids] }.
// Lo que viaja es sólo { producto_id, variantes } (ver sincronizar).
const filas = ref([])

function sincronizar () {
  form.oferta.productos = filas.value.map((f) => ({
    producto_id: f.producto_id,
    variantes: f.soloAlgunas ? [...f.variantes] : []
  }))
}

function yaEsta (productoId) {
  return filas.value.some((f) => f.producto_id === productoId)
}

async function filaDesdeProducto (productoId, variantesElegidas = []) {
  const detalle = await ProductoService.get(productoId)
  return {
    producto_id: detalle.id,
    nombre: detalle.nombre,
    precio: detalle.precio,
    disponibles: detalle.variantes,
    soloAlgunas: variantesElegidas.length > 0,
    variantes: variantesElegidas
  }
}

async function agregarProducto (producto) {
  if (!producto || yaEsta(producto.id)) return
  filas.value.push(await filaDesdeProducto(producto.id))
  sincronizar()
}

function quitarProducto (i) {
  filas.value.splice(i, 1)
  sincronizar()
}

function alternarVariante (fila, varianteId) {
  fila.variantes = fila.variantes.includes(varianteId)
    ? fila.variantes.filter((id) => id !== varianteId)
    : [...fila.variantes, varianteId]
  sincronizar()
}

function erroresDe (i) {
  return Object.entries(form.errors)
    .filter(([clave]) => clave.startsWith(`${PATH}.productos.${i}.`))
    .map(([, mensaje]) => mensaje)
}

// Búsqueda de productos activos en el servidor.
const resultados = ref([])
const buscando = ref(false)

async function buscarProductos (termino, update, abort) {
  if (!termino.trim()) {
    update(() => { resultados.value = [] })
    return
  }
  buscando.value = true
  try {
    const { data } = await ProductoService.getData({ params: { search: termino.trim(), rowsPerPage: 10, activo: 1 } })
    update(() => { resultados.value = data })
  } catch {
    abort()
  } finally {
    buscando.value = false
  }
}

// Vista previa: el precio de lista más bajo de lo elegido → con la oferta.
function previaDe (fila) {
  const valor = Number(form.oferta.valor)
  if (!valor) return null

  const elegidas = fila.soloAlgunas ? fila.disponibles.filter((v) => fila.variantes.includes(v.id)) : fila.disponibles
  const listas = elegidas.map((v) => Number(v.precio ?? fila.precio))
  const lista = listas.length ? Math.min(...listas) : Number(fila.precio)
  const oferta = form.oferta.tipo === 'porcentaje' ? Math.round(lista * (1 - valor / 100) * 100) / 100 : valor
  return { lista, oferta }
}

// ── Categorías ──
const categoriasLista = ref([])
const categorias = computed(() => opcionesPadre(categoriasLista.value))

// ── Fechas: el input trabaja en hora local, el form guarda ISO con zona ──
const inicio = computed({
  get: () => isoALocal(form.oferta.inicia_at),
  set: (local) => { form.oferta.inicia_at = localAIso(local) }
})
const fin = computed({
  get: () => isoALocal(form.oferta.termina_at),
  set: (local) => { form.oferta.termina_at = localAIso(local) }
})

onMounted(async () => {
  const [catalogo, oferta] = await Promise.all([
    CategoriaService.getData({ params: { rowsPerPage: 0 } }),
    props.id ? OfertaService.get(props.id) : null
  ])
  categoriasLista.value = catalogo.data

  if (!oferta) return

  // Las variantes disponibles de cada producto (para poder cambiar la elección).
  filas.value = await Promise.all(oferta.productos.map((p) => filaDesdeProducto(p.id, p.variantes.map((v) => v.id))))

  form.setData({
    [PATH]: {
      nombre: oferta.nombre,
      alcance: oferta.alcance,
      productos: [],
      categorias: oferta.categorias.map((c) => c.id),
      incluye_subcategorias: oferta.incluye_subcategorias,
      tipo: oferta.tipo,
      valor: oferta.valor,
      inicia_at: oferta.inicia_at,
      termina_at: oferta.termina_at,
      activa: oferta.activa
    }
  })
  sincronizar()
})

async function submit () {
  // "Sólo algunas" sin ninguna elegida se guardaría como el producto
  // COMPLETO: justo lo contrario de lo que se quiso.
  const vacia = filas.value.find((f) => f.soloAlgunas && !f.variantes.length)
  if (form.oferta.alcance === 'productos' && vacia) {
    $q.notify({ type: 'warning', message: `${vacia.nombre}: elegí al menos una variante o pasá a "Todas".`, position: 'top', timeout: 3000 })
    return
  }

  try {
    await form.submit()
    form.reset()
    filas.value = []
    emit('save')
  } catch {
    // 422: los errores quedan en form.errors y se ven en cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.oferta-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.oferta-form__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 16px;
}

.oferta-form__grow {
  flex: 1 1 200px;
  min-width: 0;
}

.oferta-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.oferta-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

.oferta-form__toggle {
  align-self: flex-start;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  overflow: hidden;
}

// Mismo radio, borde y foco que AppTextField.
.oferta-form__control {
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

.oferta-form__texto {
  font-size: 13px;
  color: var(--app-ink);
}

.oferta-form__error {
  margin: 0;
  font-size: 12px;
  color: var(--q-negative);
}

// ── Productos de la oferta ──
.oferta-form__productos {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.oferta-producto {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
}

.oferta-producto__cabecera {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.oferta-producto__nombre {
  font-weight: 600;
  color: var(--app-ink);
}

.oferta-producto__previa {
  font-size: 12.5px;
  color: var(--app-ink-2);

  strong {
    color: #DC2626;
  }
}

.oferta-producto__variantes {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.oferta-variante {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border: 1px solid var(--app-border-control);
  border-radius: 999px;
  background: var(--app-surface);
  font-size: 12px;
  color: var(--app-ink);
  cursor: pointer;

  &--activa {
    border-color: $primary;
    background: rgba($primary, 0.1);
    font-weight: 600;
    color: $primary;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
}

.oferta-variante__swatch {
  width: 10px;
  height: 10px;
  border: 1px solid var(--app-border-control);
  border-radius: 50%;
}
</style>
