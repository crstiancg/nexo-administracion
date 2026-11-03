<template>
  <form
    class="pedido-form"
    novalidate
    @submit.prevent="submit"
  >
    <!-- ── Cliente y canal ── -->
    <div class="pedido-form__row">
      <div class="pedido-form__field pedido-form__grow">
        <div class="pedido-form__labelRow">
          <label
            :id="`${uid}-cliente`"
            class="pedido-form__label"
          >Cliente</label>
          <button
            v-if="userStore.hasPermission('clientes.store')"
            type="button"
            class="pedido-form__link"
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
          class="pedido-form__error"
        >
          {{ form.errors[`${PATH}.cliente_id`] }}
        </p>
      </div>

      <div class="pedido-form__field pedido-form__canal">
        <label
          :id="`${uid}-canal`"
          class="pedido-form__label"
        >Canal</label>
        <q-select
          v-model="form.pedido.canal"
          :options="CANALES"
          :aria-labelledby="`${uid}-canal`"
          :error="Boolean(form.errors[`${PATH}.canal`])"
          :error-message="form.errors[`${PATH}.canal`]"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          emit-value
          map-options
          class="pedido-form__control"
        />
      </div>
    </div>

    <!-- ── Ítems ── -->
    <section class="pedido-form__items">
      <BuscadorVariante
        label="Agregar producto: SKU o nombre"
        :excluir="form.pedido.items.map((i) => i.variante_id)"
        @elegir="agregar"
      />

      <p
        v-if="form.errors[`${PATH}.items`]"
        class="pedido-form__error"
        role="alert"
      >
        {{ form.errors[`${PATH}.items`] }}
      </p>

      <div
        v-if="form.pedido.items.length"
        class="pedido-form__tablaWrap"
      >
        <table class="pedido-form__tabla">
          <thead>
            <tr>
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
              v-for="(item, i) in form.pedido.items"
              :key="item.variante_id"
            >
              <td>
                <div class="pedido-form__variante">
                  <span
                    class="pedido-form__swatch"
                    :style="{ background: item.variante.color?.hexadecimal }"
                  />
                  <div>
                    <div class="pedido-form__producto">
                      {{ item.variante.producto?.nombre }}
                    </div>
                    <div class="pedido-form__detalle">
                      {{ item.variante.presentacion }}<template v-if="item.variante.color"> · {{ item.variante.color.nombre }}</template> ·
                      <span class="text-mono">{{ item.variante.sku }}</span>
                    </div>
                  </div>
                </div>
                <p
                  v-if="errorDe(i, 'variante_id')"
                  class="pedido-form__error"
                >
                  {{ errorDe(i, 'variante_id') }}
                </p>
              </td>

              <td class="pedido-form__numero">
                <q-input
                  v-model="item.cantidad"
                  :aria-label="`Cantidad de ${item.variante.sku}`"
                  type="number"
                  min="0"
                  :step="item.variante.unidad?.fraccionable ? '0.001' : '1'"
                  :suffix="item.variante.unidad?.abreviatura"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorDe(i, 'cantidad'))"
                  :error-message="errorDe(i, 'cantidad')"
                  class="pedido-form__control"
                  @change="form.validate(`${PATH}.items.${i}.cantidad`)"
                />
                <!-- Aviso, no error: un pendiente todavía no toca el stock.
                     Se exige recién al confirmar. -->
                <p
                  v-if="!errorDe(i, 'cantidad') && faltaStock(item)"
                  class="pedido-form__aviso"
                >
                  Hay {{ item.variante.stock }} en tu sede
                </p>
              </td>

              <td class="pedido-form__numero">
                <q-input
                  v-model="item.precio_unitario"
                  :aria-label="`Precio de ${item.variante.sku}`"
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
                  class="pedido-form__control"
                  @change="form.validate(`${PATH}.items.${i}.precio_unitario`)"
                />
                <span
                  v-if="item.por_mayor && (item.precio_auto === undefined || item.precio_unitario === item.precio_auto)"
                  class="precio-mayor"
                >Por mayor</span>
              </td>

              <td class="text-right text-mono pedido-form__subtotal">
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
                  :aria-label="`Quitar ${item.variante.sku}`"
                  @click="quitar(i)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-else
        class="pedido-form__vacio"
      >
        Buscá los productos arriba para agregarlos al pedido.
      </p>
    </section>

    <!-- ── Totales ── -->
    <div class="pedido-form__pie">
      <AppTextField
        v-model="form.pedido.observacion"
        label="Observación (opcional)"
        type="textarea"
        autogrow
        maxlength="500"
        placeholder="Dirección de entrega, horario, envoltorio para regalo…"
        class="pedido-form__grow"
        :error="form.errors[`${PATH}.observacion`]"
        @change="form.validate(`${PATH}.observacion`)"
      />

      <dl class="pedido-form__totales">
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
            v-model="form.pedido.descuento"
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
            class="pedido-form__control pedido-form__descuento"
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

        <dt class="pedido-form__total">
          Total
        </dt>
        <dd class="pedido-form__total text-mono">
          {{ formatearPrecio(total) }}
        </dd>
      </dl>
    </div>

    <button
      type="submit"
      hidden
    />

    <!-- Alta rápida sin salir del pedido. -->
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
import { useForm } from 'laravel-precognition-vue'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppTextField from '@/components/AppTextField.vue'
import ClientesForm from '@/modules/Clientes/ClientesForm.vue'
import BuscadorVariante from '@/modules/Inventario/BuscadorVariante.vue'
import PedidoService from '@/services/PedidoService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import { desglosarIgv, ETIQUETA_IGV } from '@/utils/igv'
import BuscadorCliente from './BuscadorCliente.vue'
import formPedido, { nuevoItem, reprecio } from './FormPedido'
import { CANALES } from './constantes'

const PATH = 'pedido'

const props = defineProps({
  // null = crear; con id = editar (sólo pendientes).
  id: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

const userStore = useUserStore()
const uid = `pedido-${useId()}`

const form = props.id
  ? useForm('put', `api/pedidos/${props.id}`, formPedido)
  : useForm('post', 'api/pedidos', formPedido)

// ── Cliente ──
// El objeto completo para mostrarlo; al backend viaja sólo el id.
const cliente = ref(null)
watch(cliente, (valor) => {
  form.pedido.cliente_id = valor?.id ?? null
  // Mayorista ↔ no mayorista: los precios propuestos por el sistema cambian.
  reprecio(form.pedido.items, valor)
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

function agregar (variante) {
  form.pedido.items.push(nuevoItem(variante, cliente.value))
}

function quitar (i) {
  form.pedido.items.splice(i, 1)
}

function numero (valor) {
  const n = Number(valor)
  return valor !== '' && Number.isFinite(n) ? n : 0
}

function subtotalDe (item) {
  return Math.round(numero(item.cantidad) * numero(item.precio_unitario) * 100) / 100
}

function faltaStock (item) {
  return numero(item.cantidad) > item.variante.stock
}

// Vista previa: el total que vale es el que calcula el backend.
const subtotal = computed(() => form.pedido.items.reduce((suma, item) => suma + subtotalDe(item), 0))
const total = computed(() => Math.max(0, subtotal.value - numero(form.pedido.descuento)))

// ── Carga ──
onMounted(async () => {
  if (!props.id) return

  const pedido = await PedidoService.get(props.id)
  cliente.value = pedido.cliente
  form.setData({
    [PATH]: {
      cliente_id: pedido.cliente_id,
      canal: pedido.canal,
      descuento: Number(pedido.descuento) ? pedido.descuento : '',
      observacion: pedido.observacion ?? '',
      items: pedido.items.map((item) => ({
        variante_id: item.variante_id,
        variante: item.variante,
        cantidad: String(item.cantidad),
        precio_unitario: item.precio_unitario,
        por_mayor: Boolean(item.por_mayor)
      }))
    }
  })
})

async function submit () {
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

.pedido-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.pedido-form__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 16px;
}

.pedido-form__grow {
  flex: 1 1 260px;
  min-width: 0;
}

.pedido-form__canal {
  flex: 0 1 200px;
}

.pedido-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.pedido-form__labelRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.pedido-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

.pedido-form__link {
  padding: 0;
  border: 0;
  background: none;
  font-size: 12px;
  font-weight: 600;
  color: $primary;
  cursor: pointer;

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
}

// Mismo radio, borde y foco que AppTextField.
.pedido-form__control {
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

.pedido-form__items {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--app-border-subtle);
}

.pedido-form__tablaWrap {
  overflow-x: auto;
}

.pedido-form__tabla {
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

.pedido-form__variante {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.pedido-form__swatch {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
  margin-top: 2px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 4px;
}

.pedido-form__producto {
  font-weight: 600;
}

.pedido-form__detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.pedido-form__numero {
  width: 130px;
}

.pedido-form__subtotal {
  padding-top: 14px !important;
  font-weight: 600;
}

.pedido-form__aviso {
  margin: 4px 0 0;
  font-size: 11.5px;
  color: var(--q-warning);
}

.pedido-form__vacio {
  margin: 0;
  padding: 16px;
  border: 1px dashed var(--app-border-control);
  border-radius: 10px;
  font-size: 12px;
  text-align: center;
  color: var(--app-ink-2);
}

.pedido-form__pie {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 24px;
}

.pedido-form__totales {
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

.pedido-form__total {
  padding-top: 8px;
  border-top: 1px solid var(--app-border-subtle);
  font-size: 16px;
  font-weight: 700;
  color: var(--app-ink) !important;
}

.pedido-form__error {
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
