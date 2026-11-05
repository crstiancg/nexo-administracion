<template>
  <form
    class="cotizacion-form"
    novalidate
    @submit.prevent="submit"
  >
    <!-- ── Tipo: ítems sueltos o una PC armada por componentes ── -->
    <div class="cotizacion-form__tipo">
      <q-btn-toggle
        :model-value="form.cotizacion.tipo"
        :options="[
          { label: 'Ítems sueltos', value: 'normal', icon: 'list_alt' },
          { label: 'PC armada', value: 'pc', icon: 'computer' }
        ]"
        no-caps
        unelevated
        toggle-color="primary"
        class="cotizacion-form__toggle"
        @update:model-value="cambiarTipo"
      />
      <template v-if="form.cotizacion.tipo === 'pc'">
        <!-- Varias PCs iguales: los componentes son los de UNA. -->
        <q-input
          v-model="form.cotizacion.equipos"
          type="number"
          min="1"
          :max="MAX_EQUIPOS"
          step="1"
          label="N° de equipos"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          :error="Boolean(form.errors[`${PATH}.equipos`])"
          :error-message="form.errors[`${PATH}.equipos`]"
          class="cotizacion-form__control cotizacion-form__equipos"
          @change="form.validate(`${PATH}.equipos`)"
        />
        <q-btn-dropdown
          flat
          no-caps
          icon="bookmarks"
          label="Plantillas"
          color="primary"
          @before-show="cargarPlantillas"
        >
          <q-list
            dense
            class="cotizacion-form__plantillas"
          >
            <q-item-label header>
              Cargar una PC guardada
            </q-item-label>
            <q-item v-if="cargandoPlantillas">
              <q-item-section class="text-grey-7">
                Cargando…
              </q-item-section>
            </q-item>
            <q-item v-else-if="!plantillas.length">
              <q-item-section class="text-grey-7">
                Todavía no hay plantillas.
              </q-item-section>
            </q-item>
            <q-item
              v-for="plantilla in plantillas"
              :key="plantilla.id"
              v-close-popup
              clickable
              @click="usarPlantilla(plantilla)"
            >
              <q-item-section>
                <q-item-label>{{ plantilla.nombre }}</q-item-label>
                <q-item-label caption>
                  {{ plantilla.items.length }} componentes<template v-if="plantilla.garantia">
                    · {{ plantilla.garantia }}
                  </template>
                </q-item-label>
              </q-item-section>
              <q-item-section
                v-if="userStore.hasPermission('cotizaciones.store')"
                side
              >
                <q-btn
                  flat
                  dense
                  round
                  size="sm"
                  icon="delete_outline"
                  :aria-label="`Borrar la plantilla ${plantilla.nombre}`"
                  @click.stop="borrarPlantilla(plantilla)"
                />
              </q-item-section>
            </q-item>
            <q-separator />
            <q-item
              v-close-popup
              clickable
              :disable="!componentesLlenos.length"
              @click="abrirGuardarPlantilla"
            >
              <q-item-section avatar>
                <q-icon name="bookmark_add" />
              </q-item-section>
              <q-item-section>Guardar esta PC como plantilla</q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>
        <q-toggle
          v-model="form.cotizacion.mostrar_precios"
          label="Mostrar el precio de cada componente en la proforma"
          dense
        />
      </template>
    </div>

    <!-- ── Cliente y validez ── -->
    <div class="cotizacion-form__row">
      <div class="cotizacion-form__field cotizacion-form__grow">
        <div class="cotizacion-form__labelRow">
          <label
            :id="`${uid}-cliente`"
            class="cotizacion-form__label"
          >Cliente <span class="cotizacion-form__opcional">(opcional)</span></label>
          <button
            v-if="userStore.hasPermission('clientes.store')"
            type="button"
            class="cotizacion-form__link"
            @click="clienteDialog = true"
          >
            + Nuevo cliente
          </button>
        </div>
        <BuscadorCliente
          v-model="cliente"
          :aria-labelledby="`${uid}-cliente`"
        />
        <p
          v-if="form.errors[`${PATH}.cliente_id`]"
          class="cotizacion-form__error"
        >
          {{ form.errors[`${PATH}.cliente_id`] }}
        </p>
      </div>

      <div class="cotizacion-form__field cotizacion-form__validez">
        <label
          :for="`${uid}-validez`"
          class="cotizacion-form__label"
        >Válida hasta</label>
        <q-input
          :id="`${uid}-validez`"
          v-model="form.cotizacion.valida_hasta"
          type="date"
          :min="hoy"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          :error="Boolean(form.errors[`${PATH}.valida_hasta`])"
          :error-message="form.errors[`${PATH}.valida_hasta`]"
          :hint="form.cotizacion.valida_hasta ? '' : `Vacía: vale ${DIAS_VALIDEZ} días (hasta el ${fechaCorta(hoyLocal(DIAS_VALIDEZ))})`"
          class="cotizacion-form__control"
          @change="form.validate(`${PATH}.valida_hasta`)"
        />
      </div>
    </div>

    <!-- ── Ítems ── -->
    <section class="cotizacion-form__items">
      <div class="cotizacion-form__agregar">
        <BuscadorVariante
          class="cotizacion-form__grow"
          label="Agregar producto: SKU o nombre"
          :excluir="form.cotizacion.items.map((i) => i.variante_id).filter(Boolean)"
          @elegir="agregar"
        />
        <!-- Lo que la tienda no tiene se cotiza igual, descrito a mano. -->
        <AppButton
          variant="secondary"
          icon="add_box"
          label="Ítem que no hay en tienda"
          @click="agregarLibre()"
        />
        <AppButton
          v-if="form.cotizacion.tipo === 'pc' && faltanCasilleros"
          variant="tertiary"
          icon="playlist_add"
          label="Completar casilleros"
          @click="completarCasilleros"
        />
      </div>
      <p
        v-if="form.cotizacion.tipo === 'pc'"
        class="cotizacion-form__ayudaPc"
      >
        Llena cada casillero buscando en el catálogo (lupa) o escribiendo el componente.
        Los que queden vacíos no se guardan ni salen en la proforma.
      </p>

      <p
        v-if="form.errors[`${PATH}.items`]"
        class="cotizacion-form__error"
        role="alert"
      >
        {{ form.errors[`${PATH}.items`] }}
      </p>

      <div
        v-if="form.cotizacion.items.length"
        class="cotizacion-form__tablaWrap"
      >
        <table class="cotizacion-form__tabla">
          <thead>
            <tr>
              <th scope="col">
                <span class="sr-only">Foto</span>
              </th>
              <th scope="col">
                Producto
              </th>
              <th scope="col">
                Cantidad
              </th>
              <th scope="col">
                Precio unit.
              </th>
              <th
                scope="col"
                class="text-right"
              >
                Subtotal
              </th>
              <th scope="col">
                <span class="sr-only">Quitar</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(item, i) in form.cotizacion.items"
              :key="item.uid"
            >
              <td class="cotizacion-form__fotoCelda">
                <!-- Foto opcional: sale en la proforma junto a la descripción. -->
                <button
                  type="button"
                  :class="['cotizacion-form__fotoBtn', { 'cotizacion-form__fotoBtn--error': erroresFoto(i).length }]"
                  :aria-label="item.archivos.length ? `Cambiar la foto de ${etiquetaDe(item)}` : `Agregar una foto a ${etiquetaDe(item)}`"
                >
                  <img
                    v-if="item.archivos.length"
                    :src="item.archivos[0].miniatura_url ?? item.archivos[0].url"
                    alt=""
                    width="40"
                    height="40"
                    class="cotizacion-form__fotoThumb"
                  >
                  <q-icon
                    v-else
                    name="add_a_photo"
                    size="16px"
                  />
                  <q-menu
                    anchor="bottom left"
                    self="top left"
                  >
                    <div class="cotizacion-form__fotoPanel">
                      <AppFotos
                        v-model="item.archivos"
                        :max="1"
                        :portada="false"
                        :label="`Foto de ${etiquetaDe(item)}`"
                        :errores="erroresFoto(i)"
                      />
                    </div>
                  </q-menu>
                </button>
              </td>

              <td>
                <!-- PC armada: el casillero de este componente. -->
                <q-select
                  v-if="form.cotizacion.tipo === 'pc'"
                  v-model="item.componente"
                  :options="COMPONENTES_PC"
                  aria-label="Componente"
                  placeholder="Componente"
                  use-input
                  fill-input
                  hide-selected
                  new-value-mode="add"
                  input-debounce="0"
                  maxlength="40"
                  dense
                  borderless
                  hide-bottom-space
                  class="cotizacion-form__componente"
                  @input-value="(v) => { item.componente = v || null }"
                />
                <!-- Libre: lo que la tienda no tiene, descrito a mano. -->
                <template v-if="!item.variante">
                  <div class="cotizacion-form__libre">
                    <q-input
                      v-model="item.descripcion"
                      aria-label="Descripción del ítem"
                      placeholder="Describe el producto o servicio"
                      maxlength="200"
                      dense
                      outlined
                      autogrow
                      hide-bottom-space
                      no-error-icon
                      :error="Boolean(errorDe(i, 'descripcion') || errorDe(i, 'variante_id'))"
                      :error-message="errorDe(i, 'descripcion') || errorDe(i, 'variante_id')"
                      class="cotizacion-form__control cotizacion-form__grow"
                    />
                    <q-input
                      v-model="item.unidad"
                      aria-label="Unidad"
                      placeholder="und"
                      maxlength="20"
                      dense
                      outlined
                      hide-bottom-space
                      class="cotizacion-form__control cotizacion-form__unidad"
                    />
                    <!-- Del catálogo: reemplaza la fila y conserva el casillero. -->
                    <q-btn
                      flat
                      dense
                      round
                      icon="search"
                      size="sm"
                      color="grey-7"
                      :aria-label="`Buscar ${item.componente ?? 'el producto'} en el catálogo`"
                    >
                      <q-tooltip>Buscar en el catálogo</q-tooltip>
                      <q-menu
                        anchor="bottom right"
                        self="top right"
                      >
                        <div class="cotizacion-form__buscarPanel">
                          <BuscadorVariante
                            :label="`${item.componente ?? 'Producto'}: SKU o nombre`"
                            :excluir="form.cotizacion.items.map((otro) => otro.variante_id).filter(Boolean)"
                            @elegir="(variante) => usarDelCatalogo(i, variante)"
                          />
                        </div>
                      </q-menu>
                    </q-btn>
                  </div>
                  <span
                    v-if="!casilleroVacio(item)"
                    class="cotizacion-form__encargo"
                  >Por encargo · no pasa al pedido</span>
                </template>
                <div
                  v-else
                  class="cotizacion-form__variante">
                  <span
                    class="cotizacion-form__swatch"
                    :style="{ background: item.variante.color?.hexadecimal }"
                  />
                  <div>
                    <div class="cotizacion-form__producto">
                      {{ item.variante.producto?.nombre }}
                    </div>
                    <div class="cotizacion-form__detalle">
                      {{ item.variante.presentacion }}<template v-if="item.variante.color"> · {{ item.variante.color.nombre }}</template> ·
                      <span class="text-mono">{{ item.variante.sku }}</span>
                    </div>
                  </div>
                </div>
                <p
                  v-if="item.variante && errorDe(i, 'variante_id')"
                  class="cotizacion-form__error"
                >
                  {{ errorDe(i, 'variante_id') }}
                </p>
              </td>

              <td class="cotizacion-form__numero">
                <q-input
                  v-model="item.cantidad"
                  :aria-label="`Cantidad de ${etiquetaDe(item)}`"
                  type="number"
                  min="0"
                  :step="!item.variante || item.variante.unidad?.fraccionable ? '0.001' : '1'"
                  :suffix="item.variante ? item.variante.unidad?.abreviatura : item.unidad"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorDe(i, 'cantidad'))"
                  :error-message="errorDe(i, 'cantidad')"
                  class="cotizacion-form__control"
                  @change="form.validate(`${PATH}.items.${i}.cantidad`)"
                />
                <!-- Aviso, no error: una cotización no reserva stock. -->
                <p
                  v-if="!errorDe(i, 'cantidad') && faltaStock(item)"
                  class="cotizacion-form__aviso"
                >
                  Hay {{ item.variante.stock }} en tu sede
                </p>
              </td>

              <td class="cotizacion-form__numero">
                <q-input
                  v-model="item.precio_unitario"
                  :aria-label="`Precio de ${etiquetaDe(item)}`"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="S/"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorDe(i, 'precio_unitario'))"
                  :error-message="errorDe(i, 'precio_unitario')"
                  class="cotizacion-form__control"
                  @change="form.validate(`${PATH}.items.${i}.precio_unitario`)"
                />
                <span
                  v-if="item.por_mayor && (item.precio_auto === undefined || item.precio_unitario === item.precio_auto)"
                  class="precio-mayor"
                >Por mayor</span>
              </td>

              <td class="text-right text-mono cotizacion-form__subtotal">
                {{ formatearPrecio(subtotalDe(item)) }}
              </td>

              <td class="text-right">
                <q-btn
                  flat
                  dense
                  round
                  icon="close"
                  size="sm"
                  color="grey-7"
                  :aria-label="`Quitar ${etiquetaDe(item)}`"
                  @click="quitar(i)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-else
        class="cotizacion-form__vacio"
      >
        Busca los productos arriba para agregarlos a la cotización, o agrega uno que no hay en tienda.
      </p>
    </section>

    <!-- ── Totales ── -->
    <div class="cotizacion-form__pie">
      <div class="cotizacion-form__grow cotizacion-form__textos">
        <!-- Se elige una opción o se escribe otra (Enter para usarla). -->
        <q-select
          v-model="form.cotizacion.forma_pago"
          :options="FORMAS_PAGO"
          label="Forma de pago"
          use-input
          fill-input
          hide-selected
          new-value-mode="add"
          input-debounce="0"
          maxlength="100"
          dense
          outlined
          hide-bottom-space
          :error="Boolean(form.errors[`${PATH}.forma_pago`])"
          :error-message="form.errors[`${PATH}.forma_pago`]"
          class="cotizacion-form__control"
          @input-value="(v) => { form.cotizacion.forma_pago = v }"
        />
        <q-select
          v-model="form.cotizacion.entrega"
          :options="ENTREGAS"
          label="Entrega"
          use-input
          fill-input
          hide-selected
          new-value-mode="add"
          input-debounce="0"
          maxlength="250"
          dense
          outlined
          hide-bottom-space
          :error="Boolean(form.errors[`${PATH}.entrega`])"
          :error-message="form.errors[`${PATH}.entrega`]"
          class="cotizacion-form__control"
          @input-value="(v) => { form.cotizacion.entrega = v }"
        />
        <q-select
          v-model="form.cotizacion.garantia"
          :options="GARANTIAS"
          label="Garantía"
          use-input
          fill-input
          hide-selected
          new-value-mode="add"
          input-debounce="0"
          maxlength="100"
          clearable
          dense
          outlined
          hide-bottom-space
          :error="Boolean(form.errors[`${PATH}.garantia`])"
          :error-message="form.errors[`${PATH}.garantia`]"
          class="cotizacion-form__control"
          @input-value="(v) => { form.cotizacion.garantia = v }"
          @clear="form.cotizacion.garantia = ''"
        />
        <AppTextField
          v-model="form.cotizacion.condiciones"
          label="Otras condiciones (opcional, salen impresas)"
          type="textarea"
          autogrow
          maxlength="500"
          placeholder="Incluye instalación y configuración."
          :error="form.errors[`${PATH}.condiciones`]"
          @change="form.validate(`${PATH}.condiciones`)"
        />
        <AppTextField
          v-model="form.cotizacion.observacion"
          label="Nota interna (opcional, no se imprime)"
          type="textarea"
          autogrow
          maxlength="500"
          :error="form.errors[`${PATH}.observacion`]"
          @change="form.validate(`${PATH}.observacion`)"
        />
      </div>

      <dl class="cotizacion-form__totales">
        <!-- PC para varios equipos: los ítems de arriba son de uno. -->
        <template v-if="equipos > 1">
          <dt>Precio por equipo</dt>
          <dd class="text-mono">
            {{ formatearPrecio(subtotalEquipo) }}
          </dd>
          <dt>N° de equipos</dt>
          <dd class="text-mono">
            × {{ equipos }}
          </dd>
        </template>
        <dt>Subtotal</dt>
        <dd class="text-mono">
          {{ formatearPrecio(subtotal) }}
        </dd>

        <dt>
          <label :for="`${uid}-descuento`">Descuento</label>
        </dt>
        <dd>
          <q-input
            :id="`${uid}-descuento`"
            v-model="form.cotizacion.descuento"
            type="number"
            min="0"
            step="0.01"
            prefix="S/"
            placeholder="0.00"
            dense
            outlined
            hide-bottom-space
            no-error-icon
            :error="Boolean(form.errors[`${PATH}.descuento`])"
            :error-message="form.errors[`${PATH}.descuento`]"
            class="cotizacion-form__control cotizacion-form__descuento"
            @change="form.validate(`${PATH}.descuento`)"
          />
        </dd>

        <dt>Op. gravada</dt>
        <dd class="text-mono">
          {{ formatearPrecio(desglosarIgv(total).opGravada) }}
        </dd>
        <dt>{{ ETIQUETA_IGV }}</dt>
        <dd class="text-mono">
          {{ formatearPrecio(desglosarIgv(total).igv) }}
        </dd>

        <dt class="cotizacion-form__total">
          Total
        </dt>
        <dd class="cotizacion-form__total text-mono">
          {{ formatearPrecio(total) }}
        </dd>
      </dl>
    </div>

    <button
      type="submit"
      hidden
    />

    <!-- Guardar los componentes como plantilla de PC. -->
    <AppDialog
      v-model="plantillaDialog"
      title="Guardar como plantilla"
    >
      <AppTextField
        v-model="nombrePlantilla"
        label="Nombre de la plantilla"
        maxlength="100"
        placeholder="PC Oficina Core i5"
        autofocus
        :error="errorPlantilla"
        @keydown.enter.prevent="guardarPlantilla"
      />
      <p class="cotizacion-form__opcional">
        Se guardan {{ componentesLlenos.length }} componentes<template v-if="form.cotizacion.garantia">
          y la garantía
        </template>. Los productos del catálogo se cargan siempre con el precio del día.
      </p>
      <p
        v-if="reemplazaPlantilla"
        class="cotizacion-form__aviso"
      >
        Ya hay una plantilla con ese nombre: se reemplaza.
      </p>

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="plantillaDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar plantilla"
          :loading="guardandoPlantilla"
          :disable="!nombrePlantilla.trim()"
          @click="guardarPlantilla"
        />
      </template>
    </AppDialog>

    <!-- Alta rápida sin salir de la cotización. -->
    <AppDialog
      v-model="clienteDialog"
      title="Nuevo cliente"
      persistent
    >
      <ClientesForm
        v-if="clienteDialog"
        ref="clienteFormRef"
        @save="clienteCreado"
        @existente="clienteCreado"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="clienteDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar cliente"
          :loading="clienteFormRef?.form.processing"
          @click="clienteFormRef.submit()"
        />
      </template>
    </AppDialog>
  </form>
</template>

<script setup>
import { computed, onMounted, ref, useId, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useForm } from 'laravel-precognition-vue'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFotos from '@/components/AppFotos.vue'
import AppTextField from '@/components/AppTextField.vue'
import ClientesForm from '@/modules/Clientes/ClientesForm.vue'
import BuscadorVariante from '@/modules/Inventario/BuscadorVariante.vue'
import CotizacionService from '@/services/CotizacionService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import { desglosarIgv, ETIQUETA_IGV } from '@/utils/igv'
import BuscadorCliente from '@/modules/Pedidos/BuscadorCliente.vue'
import { nuevoItem, reprecio } from '@/modules/Pedidos/FormPedido'
import formCotizacion, {
  casilleroVacio, COMPONENTES_PC, conUid, DIAS_VALIDEZ, ENTREGAS, FORMAS_PAGO, GARANTIAS, MAX_EQUIPOS, nuevoItemLibre
} from './FormCotizacion'
import { fechaCorta, hoyLocal } from './constantes'

const PATH = 'cotizacion'

const props = defineProps({
  // null = crear; con id = editar (sólo pendientes).
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

const $q = useQuasar()
const userStore = useUserStore()
const uid = `cotizacion-${useId()}`
const hoy = hoyLocal()

// Editar va por POST + _method=PUT (ver FormCotizacion.js).
const form = props.id
  ? useForm('post', `api/cotizaciones/${props.id}`, () => formCotizacion(true))
  : useForm('post', 'api/cotizaciones', () => formCotizacion())

// ── Cliente ──
// El objeto completo para mostrarlo; al backend viaja sólo el id.
const cliente = ref(null)
watch(cliente, (valor) => {
  form.cotizacion.cliente_id = valor?.id ?? null
  // Mayorista ↔ no mayorista: los precios propuestos por el sistema cambian.
  reprecio(form.cotizacion.items, valor)
})

const clienteDialog = ref(false)
const clienteFormRef = ref()

function clienteCreado (nuevo) {
  clienteDialog.value = false
  if (nuevo) cliente.value = nuevo
}

// ── Ítems ──
function errorDe (i, campo) {
  return form.errors[`${PATH}.items.${i}.${campo}`]
}

function erroresFoto (i) {
  return Object.entries(form.errors)
    .filter(([clave]) => clave.startsWith(`${PATH}.items.${i}.archivos`))
    .map(([, mensaje]) => mensaje)
}

/** Para los aria-label: SKU del catálogo o la descripción del libre. */
function etiquetaDe (item) {
  return item.variante?.sku ?? (item.descripcion || 'ítem sin descripción')
}

function agregar (variante) {
  form.cotizacion.items.push(conUid(nuevoItem(variante, cliente.value)))
}

function agregarLibre () {
  form.cotizacion.items.push(nuevoItemLibre())
}

// ── PC armada ──
const faltanCasilleros = computed(() => COMPONENTES_PC.some((c) => !form.cotizacion.items.some((item) => item.componente === c)))

/** Agrega, en orden, los casilleros que todavía no están. */
function completarCasilleros () {
  const usados = new Set(form.cotizacion.items.map((item) => item.componente))
  form.cotizacion.items.push(...COMPONENTES_PC.filter((c) => !usados.has(c)).map((c) => nuevoItemLibre(c)))
}

function cambiarTipo (tipo) {
  form.cotizacion.tipo = tipo
  if (tipo === 'pc') {
    completarCasilleros()
  } else {
    // Ítems sueltos: los casilleros vacíos sobran y es un solo "equipo".
    form.cotizacion.items = form.cotizacion.items.filter((item) => !casilleroVacio(item))
    form.cotizacion.equipos = 1
  }
}

// ── Plantillas de PC ──
const plantillas = ref([])
const cargandoPlantillas = ref(false)
const componentesLlenos = computed(() => form.cotizacion.items.filter((item) => !casilleroVacio(item)))

async function cargarPlantillas () {
  cargandoPlantillas.value = !plantillas.value.length
  try {
    plantillas.value = await CotizacionService.plantillas()
  } finally {
    cargandoPlantillas.value = false
  }
}

/** Del catálogo, con el precio de hoy (y el de mayorista si toca); libres, como se guardaron. */
function itemDePlantilla (item) {
  if (item.variante) {
    return conUid({ ...nuevoItem(item.variante, cliente.value), componente: item.componente, cantidad: String(item.cantidad) })
  }
  return {
    ...nuevoItemLibre(item.componente),
    descripcion: item.descripcion ?? '',
    unidad: item.unidad || 'und',
    cantidad: String(item.cantidad),
    precio_unitario: item.precio_unitario === null ? '' : String(item.precio_unitario)
  }
}

function usarPlantilla (plantilla) {
  const cargar = () => {
    form.cotizacion.items = plantilla.items.map(itemDePlantilla)
    if (plantilla.garantia) form.cotizacion.garantia = plantilla.garantia
    const faltan = plantilla.items.filter((item) => item.no_disponible).length
    if (faltan) {
      $q.notify({
        message: faltan === 1
          ? 'Un componente de la plantilla ya no se vende en tu sede: quedó vacío para elegir otro.'
          : `${faltan} componentes de la plantilla ya no se venden en tu sede: quedaron vacíos para elegir otros.`,
        icon: 'info',
        color: 'warning',
        textColor: 'dark'
      })
    }
  }
  if (!componentesLlenos.value.length) return cargar()

  $q.dialog({
    title: 'Cargar plantilla',
    message: `Los componentes actuales se reemplazan por los de “${plantilla.nombre}”.`,
    cancel: { label: 'Cancelar', flat: true, noCaps: true },
    ok: { label: 'Cargar', noCaps: true, unelevated: true },
    persistent: true
  }).onOk(cargar)
}

const plantillaDialog = ref(false)
const nombrePlantilla = ref('')
const errorPlantilla = ref('')
const guardandoPlantilla = ref(false)
const reemplazaPlantilla = computed(() => {
  const nombre = nombrePlantilla.value.trim().toLowerCase()
  return Boolean(nombre) && plantillas.value.some((p) => p.nombre.toLowerCase() === nombre)
})

function abrirGuardarPlantilla () {
  nombrePlantilla.value = ''
  errorPlantilla.value = ''
  plantillaDialog.value = true
}

async function guardarPlantilla () {
  if (guardandoPlantilla.value || !nombrePlantilla.value.trim()) return
  guardandoPlantilla.value = true
  errorPlantilla.value = ''
  try {
    const { nombre } = await CotizacionService.guardarPlantilla({
      nombre: nombrePlantilla.value,
      garantia: form.cotizacion.garantia || null,
      items: componentesLlenos.value.map((item) => ({
        componente: item.componente,
        variante_id: item.variante_id ?? null,
        descripcion: item.variante ? null : item.descripcion,
        unidad: item.variante ? null : item.unidad,
        cantidad: item.cantidad,
        precio_unitario: item.precio_unitario === '' ? null : item.precio_unitario
      }))
    })
    plantillaDialog.value = false
    $q.notify({ message: `Plantilla “${nombre}” guardada`, icon: 'bookmark_added', color: 'positive' })
  } catch (error) {
    const { data } = error.response ?? {}
    errorPlantilla.value = Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message ?? 'No se pudo guardar la plantilla.'
  } finally {
    guardandoPlantilla.value = false
  }
}

function borrarPlantilla (plantilla) {
  $q.dialog({
    title: 'Borrar plantilla',
    message: `¿Borrar la plantilla “${plantilla.nombre}”? Las cotizaciones hechas con ella no cambian.`,
    cancel: { label: 'Cancelar', flat: true, noCaps: true },
    ok: { label: 'Borrar', color: 'negative', noCaps: true, unelevated: true },
    persistent: true
  }).onOk(async () => {
    await CotizacionService.borrarPlantilla(plantilla.id)
    plantillas.value = plantillas.value.filter((p) => p.id !== plantilla.id)
  })
}

/** El casillero se llena con un producto del catálogo (mantiene el componente y la cantidad). */
function usarDelCatalogo (i, variante) {
  const anterior = form.cotizacion.items[i]
  form.cotizacion.items.splice(i, 1, conUid({
    ...nuevoItem(variante, cliente.value),
    componente: anterior.componente,
    cantidad: anterior.cantidad || '1',
    archivos: anterior.archivos
  }))
}

function quitar (i) {
  form.cotizacion.items.splice(i, 1)
}

function numero (valor) {
  const n = Number(valor)
  return valor !== '' && Number.isFinite(n) ? n : 0
}

function subtotalDe (item) {
  return Math.round(numero(item.cantidad) * numero(item.precio_unitario) * 100) / 100
}

function faltaStock (item) {
  return Boolean(item.variante) && numero(item.cantidad) > item.variante.stock
}

// Vista previa: el total que vale es el que calcula el backend.
const equipos = computed(() => (form.cotizacion.tipo === 'pc' ? Math.max(1, Math.trunc(numero(form.cotizacion.equipos))) : 1))
const subtotalEquipo = computed(() => form.cotizacion.items.reduce((suma, item) => suma + subtotalDe(item), 0))
const subtotal = computed(() => Math.round(subtotalEquipo.value * equipos.value * 100) / 100)
const total = computed(() => Math.max(0, subtotal.value - numero(form.cotizacion.descuento)))

// ── Carga ──
onMounted(async () => {
  if (!props.id) return

  const cotizacion = await CotizacionService.get(props.id)
  cliente.value = cotizacion.cliente
  form.setData({
    _method: 'PUT',
    [PATH]: {
      tipo: cotizacion.tipo ?? 'normal',
      mostrar_precios: cotizacion.mostrar_precios ?? true,
      equipos: cotizacion.equipos ?? 1,
      cliente_id: cotizacion.cliente_id,
      // Una vencida se edita para renovarla: la validez queda vacía (y si
      // no se pone otra, vale DIAS_VALIDEZ días desde hoy).
      valida_hasta: cotizacion.valida_hasta < hoy ? '' : cotizacion.valida_hasta,
      forma_pago: cotizacion.forma_pago ?? '',
      entrega: cotizacion.entrega ?? '',
      garantia: cotizacion.garantia ?? '',
      condiciones: cotizacion.condiciones ?? '',
      descuento: Number(cotizacion.descuento) ? cotizacion.descuento : '',
      observacion: cotizacion.observacion ?? '',
      items: cotizacion.items.map((item) => conUid({
        componente: item.componente,
        variante_id: item.variante_id,
        variante: item.variante,
        descripcion: item.descripcion ?? '',
        unidad: item.unidad ?? '',
        cantidad: String(item.cantidad),
        precio_unitario: item.precio_unitario,
        // Una guardada viaja como { id }: el backend la conserva.
        archivos: (item.archivos ?? []).map(({ id, url, miniatura_url: miniaturaUrl, nombre }) => ({ id, url, miniatura_url: miniaturaUrl, nombre }))
      }))
    }
  })
})

// Sin cliente se puede ("cotización sin nombre"), pero se avisa antes: a
// veces es un olvido. Sin validez, el backend le da DIAS_VALIDEZ días.
function submit () {
  if (form.cotizacion.cliente_id) {
    guardar()
    return
  }

  $q.notify({
    message: 'Vas a guardar la cotización sin cliente',
    caption: 'Sale impresa sin nombre. Si es para alguien en particular, elígelo o créalo con “+ Nuevo cliente”.',
    icon: 'person_off',
    color: 'warning',
    textColor: 'dark',
    position: 'center',
    timeout: 0,
    multiLine: true,
    actions: [
      { label: 'Elegir cliente', color: 'dark' },
      { label: 'Guardar sin cliente', color: 'dark', handler: guardar }
    ]
  })
}

async function guardar () {
  // Los casilleros de la PC que quedaron vacíos no viajan.
  form.cotizacion.items = form.cotizacion.items.filter((item) => !casilleroVacio(item))
  try {
    const respuesta = await form.submit()
    form.reset()
    cliente.value = null
    emit('save', respuesta?.data)
  } catch {
    // 422: los errores quedan en form.errors y se ven en cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.precio-mayor {
  display: inline-block;
  margin-top: 3px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--app-brand-soft);
  font-size: 10.5px;
  font-weight: 700;
  line-height: 17px;
  color: var(--app-brand-soft-ink);
}

.cotizacion-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cotizacion-form__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 16px;
}

.cotizacion-form__grow {
  flex: 1 1 260px;
  min-width: 0;
}

.cotizacion-form__validez {
  flex: 0 1 200px;
}

.cotizacion-form__textos {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cotizacion-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.cotizacion-form__labelRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.cotizacion-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

.cotizacion-form__opcional {
  font-weight: 400;
  color: var(--app-ink-2);
}

.cotizacion-form__requerido {
  color: var(--q-negative);
}

.cotizacion-form__link {
  padding: 0;
  border: 0;
  background: none;
  font-size: 12px;
  font-weight: 600;
  color: var(--q-primary);
  cursor: pointer;

  &:focus-visible {
    outline: 2px solid var(--q-primary);
    outline-offset: 2px;
  }
}

.cotizacion-form__tipo {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 20px;
}

.cotizacion-form__equipos {
  width: 130px;
}

.cotizacion-form__plantillas {
  min-width: 280px;
}

.cotizacion-form__toggle {
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  overflow: hidden;
}

.cotizacion-form__ayudaPc {
  margin: -4px 0 0;
  font-size: 12px;
  color: var(--app-ink-2);
}

// El casillero va arriba de la descripción, como una etiqueta editable.
.cotizacion-form__componente {
  margin-bottom: 2px;

  :deep(.q-field__control),
  :deep(.q-field__native) {
    min-height: 22px;
    padding: 0;
  }

  :deep(input) {
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: .4px;
    text-transform: uppercase;
    color: var(--q-primary);
  }
}

.cotizacion-form__buscarPanel {
  width: 360px;
  padding: 12px;
}

.cotizacion-form__agregar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 12px;
}

.cotizacion-form__libre {
  display: flex;
  align-items: flex-start;
  gap: 6px;
}

.cotizacion-form__unidad {
  flex: 0 0 70px;
}

.cotizacion-form__encargo {
  display: inline-block;
  margin-top: 4px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--app-brand-soft);
  font-size: 10.5px;
  font-weight: 700;
  line-height: 17px;
  color: var(--app-brand-soft-ink);
}

.cotizacion-form__fotoCelda {
  width: 52px;
}

// Como el botón de fotos de cada presentación en Productos.
.cotizacion-form__fotoBtn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 1px solid var(--app-border-control);
  border-radius: 10px;
  overflow: hidden;
  background: var(--app-surface);
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    border-color: var(--app-border-control-hover);
  }

  &:focus-visible {
    outline: 2px solid var(--q-primary);
    outline-offset: 2px;
  }

  &--error {
    border-color: var(--q-negative);
  }
}

.cotizacion-form__fotoThumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cotizacion-form__fotoPanel {
  width: 280px;
  padding: 12px;
}

// Mismo radio, borde y foco que AppTextField.
.cotizacion-form__control {
  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }

  &.q-field--focused :deep(.q-field__control) {
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--q-primary) 12%, transparent);
  }
}

.cotizacion-form__items {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--app-border-subtle);
}

.cotizacion-form__tablaWrap {
  overflow-x: auto;
}

.cotizacion-form__tabla {
  width: 100%;
  min-width: 600px;
  border-collapse: collapse;

  th {
    padding: 0 6px 6px;
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    color: var(--app-ink-2);
    white-space: nowrap;
  }

  td {
    padding: 6px;
    vertical-align: top;
    border-top: 1px solid var(--app-border-subtle);
    font-size: 13px;
    color: var(--app-ink);
  }

  .text-right {
    text-align: right;
  }
}

.cotizacion-form__variante {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.cotizacion-form__swatch {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
  margin-top: 2px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 4px;
}

.cotizacion-form__producto {
  font-weight: 600;
}

.cotizacion-form__detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.cotizacion-form__numero {
  width: 130px;
}

.cotizacion-form__subtotal {
  padding-top: 14px !important;
  font-weight: 600;
}

.cotizacion-form__aviso {
  margin: 4px 0 0;
  font-size: 11.5px;
  color: var(--q-warning);
}

.cotizacion-form__vacio {
  margin: 0;
  padding: 16px;
  border: 1px dashed var(--app-border-control);
  border-radius: 10px;
  font-size: 12px;
  text-align: center;
  color: var(--app-ink-2);
}

.cotizacion-form__pie {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 24px;
}

.cotizacion-form__totales {
  display: grid;
  grid-template-columns: auto 150px;
  align-items: center;
  gap: 8px 16px;
  margin: 0;
  font-size: 13px;

  dt {
    color: var(--app-ink-2);
  }

  dd {
    margin: 0;
    text-align: right;
    color: var(--app-ink);
  }
}

.cotizacion-form__total {
  padding-top: 8px;
  border-top: 1px solid var(--app-border-subtle);
  font-size: 16px;
  font-weight: 700;
  color: var(--app-ink) !important;
}

.cotizacion-form__error {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--q-negative);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
</style>
