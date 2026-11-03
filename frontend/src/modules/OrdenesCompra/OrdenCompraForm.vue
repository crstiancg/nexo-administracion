<template>
  <form
    class="oc-form"
    novalidate
    @submit.prevent="submit"
  >
    <p class="oc-form__ayuda">
      Lo que le pedís al proveedor. No mueve stock: la mercadería entra cuando llega y registrás la recepción
      (desde el pedido, con “Registrar recepción”).
    </p>

    <!-- ── Proveedor y entrega ── -->
    <div class="oc-form__row">
      <div class="oc-form__field oc-form__grow">
        <div class="oc-form__labelRow">
          <label
            :id="`${uid}-proveedor`"
            class="oc-form__label"
          >Proveedor</label>
          <button
            v-if="userStore.hasPermission('proveedores.store')"
            type="button"
            class="oc-form__link"
            @click="proveedorDialog = true"
          >
            + Nuevo proveedor
          </button>
        </div>
        <BuscadorProveedor
          v-model="proveedor"
          :aria-labelledby="`${uid}-proveedor`"
        />
        <p
          v-if="form.errors[`${PATH}.proveedor_id`]"
          class="oc-form__error"
        >
          {{ form.errors[`${PATH}.proveedor_id`] }}
        </p>
      </div>

      <div class="oc-form__field oc-form__entrega">
        <label
          :for="`${uid}-entrega`"
          class="oc-form__label"
        >Entrega esperada (opcional)</label>
        <q-input
          :id="`${uid}-entrega`"
          v-model="form.orden.fecha_entrega"
          type="date"
          :min="hoy"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          :error="Boolean(form.errors[`${PATH}.fecha_entrega`])"
          :error-message="form.errors[`${PATH}.fecha_entrega`]"
          class="oc-form__control"
          @change="form.validate(`${PATH}.fecha_entrega`)"
        />
      </div>
    </div>

    <!-- ── Ítems ── -->
    <section class="oc-form__items">
      <BuscadorVariante
        label="Agregar producto: SKU o nombre"
        :excluir="form.orden.items.map((i) => i.variante_id)"
        @elegir="agregar"
      />

      <p
        v-if="form.errors[`${PATH}.items`]"
        class="oc-form__error"
        role="alert"
      >
        {{ form.errors[`${PATH}.items`] }}
      </p>

      <div
        v-if="form.orden.items.length"
        class="oc-form__tablaWrap"
      >
        <table class="oc-form__tabla">
          <thead>
            <tr>
              <th scope="col">
                Producto
              </th>
              <th
                scope="col"
                class="text-right"
              >
                Stock / mín.
              </th>
              <th scope="col">
                Cantidad
              </th>
              <th scope="col">
                Costo unit. (opcional)
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
              v-for="(item, i) in form.orden.items"
              :key="item.variante_id"
            >
              <td>
                <div class="oc-form__producto">
                  {{ item.variante.producto?.nombre }}
                </div>
                <div class="oc-form__detalle">
                  {{ item.variante.presentacion }}<template v-if="item.variante.color"> · {{ item.variante.color.nombre }}</template> ·
                  <span class="text-mono">{{ item.variante.sku }}</span>
                </div>
                <p
                  v-if="errorDe(i, 'variante_id')"
                  class="oc-form__error"
                >
                  {{ errorDe(i, 'variante_id') }}
                </p>
              </td>

              <!-- Para decidir cuánto pedir sin salir de la orden. -->
              <td class="text-right text-mono oc-form__stock">
                {{ formatearCantidad(item.variante.stock ?? 0) }}
                <template v-if="Number(item.variante.stock_minimo)">
                  / {{ formatearCantidad(item.variante.stock_minimo) }}
                </template>
              </td>

              <td class="oc-form__numero">
                <q-input
                  v-model="item.cantidad"
                  :aria-label="`Cantidad de ${item.variante.sku}`"
                  type="number"
                  min="0"
                  :step="item.variante.unidad?.fraccionable ? '0.001' : '1'"
                  :suffix="item.variante.unidad?.abreviatura"
                  placeholder="0"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorDe(i, 'cantidad'))"
                  :error-message="errorDe(i, 'cantidad')"
                  class="oc-form__control"
                  @change="form.validate(`${PATH}.items.${i}.cantidad`)"
                />
              </td>

              <td class="oc-form__numero">
                <q-input
                  v-model="item.costo_unitario"
                  :aria-label="`Costo de ${item.variante.sku}`"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="S/"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorDe(i, 'costo_unitario'))"
                  :error-message="errorDe(i, 'costo_unitario')"
                  class="oc-form__control"
                  @change="form.validate(`${PATH}.items.${i}.costo_unitario`)"
                />
              </td>

              <td class="text-right text-mono oc-form__subtotal">
                {{ item.costo_unitario === '' ? '—' : formatearPrecio(subtotalDe(item)) }}
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
        class="oc-form__vacio"
      >
        Buscá los productos arriba, o creá el pedido desde <strong>Por reponer</strong> marcando lo que falta.
      </p>
    </section>

    <!-- ── Pie ── -->
    <div class="oc-form__pie">
      <div class="oc-form__grow oc-form__textos">
        <AppTextField
          v-model="form.orden.condiciones"
          label="Condiciones (salen impresas)"
          type="textarea"
          autogrow
          maxlength="500"
          placeholder="Entrega en almacén de la sede. Pago a 30 días con factura."
          :error="form.errors[`${PATH}.condiciones`]"
          @change="form.validate(`${PATH}.condiciones`)"
        />
        <AppTextField
          v-model="form.orden.observacion"
          label="Nota interna (opcional, no se imprime)"
          type="textarea"
          autogrow
          maxlength="500"
          :error="form.errors[`${PATH}.observacion`]"
          @change="form.validate(`${PATH}.observacion`)"
        />
      </div>

      <dl class="oc-form__totales">
        <dt class="oc-form__total">
          Total estimado
        </dt>
        <dd class="oc-form__total text-mono">
          {{ formatearPrecio(total) }}
        </dd>
        <dd
          v-if="sinCosto"
          class="oc-form__nota"
        >
          {{ sinCosto }} {{ sinCosto === 1 ? 'producto' : 'productos' }} sin costo: no suman.
        </dd>
      </dl>
    </div>

    <button
      type="submit"
      hidden
    />

    <AppDialog
      v-model="proveedorDialog"
      title="Nuevo proveedor"
      persistent
    >
      <ProveedoresForm
        v-if="proveedorDialog"
        ref="proveedorFormRef"
        @save="proveedorCreado"
        @existente="proveedorCreado"
      />

      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="proveedorDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar proveedor"
          :loading="proveedorFormRef?.form.processing"
          @click="proveedorFormRef.submit()"
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
import BuscadorProveedor from '@/modules/Compras/BuscadorProveedor.vue'
import BuscadorVariante from '@/modules/Inventario/BuscadorVariante.vue'
import ProveedoresForm from '@/modules/Proveedores/ProveedoresForm.vue'
import OrdenCompraService from '@/services/OrdenCompraService'
import { useUserStore } from '@/stores/user-store'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'
import formOrdenCompra, { nuevoItem } from './FormOrdenCompra'
import { hoyLocal } from './constantes'

const PATH = 'orden'

const props = defineProps({
  // null = crear; con id = editar (sólo pendientes).
  id: {
    type: Number,
    default: null
  },
  // Al crear desde Por reponer: las presentaciones marcadas.
  iniciales: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['save'])

const userStore = useUserStore()
const uid = `oc-${useId()}`
const hoy = hoyLocal()

const form = props.id
  ? useForm('put', `api/ordenes-compra/${props.id}`, formOrdenCompra)
  : useForm('post', 'api/ordenes-compra', formOrdenCompra)

// ── Proveedor ──
// El objeto completo para mostrarlo; al backend viaja sólo el id.
const proveedor = ref(null)
watch(proveedor, (valor) => { form.orden.proveedor_id = valor?.id ?? null })

const proveedorDialog = ref(false)
const proveedorFormRef = ref()

function proveedorCreado (nuevo) {
  proveedorDialog.value = false
  if (nuevo) proveedor.value = nuevo
}

// ── Ítems ──
function errorDe (i, campo) {
  return form.errors[`${PATH}.items.${i}.${campo}`]
}

function agregar (variante) {
  form.orden.items.push(nuevoItem(variante))
}

function quitar (i) {
  form.orden.items.splice(i, 1)
}

function numero (valor) {
  const n = Number(valor)
  return valor !== '' && Number.isFinite(n) ? n : 0
}

function subtotalDe (item) {
  return Math.round(numero(item.cantidad) * numero(item.costo_unitario) * 100) / 100
}

// Vista previa: el total que vale es el que calcula el backend.
const total = computed(() => form.orden.items.reduce((suma, item) => suma + subtotalDe(item), 0))
const sinCosto = computed(() => form.orden.items.filter((i) => i.costo_unitario === '').length)

// ── Carga ──
props.iniciales.forEach(agregar)

onMounted(async () => {
  if (!props.id) return

  const orden = await OrdenCompraService.get(props.id)
  proveedor.value = orden.proveedor
  form.setData({
    [PATH]: {
      proveedor_id: orden.proveedor_id,
      fecha_entrega: orden.fecha_entrega ?? '',
      condiciones: orden.condiciones ?? '',
      observacion: orden.observacion ?? '',
      items: orden.items.map((item) => ({
        variante_id: item.variante_id,
        variante: item.variante,
        cantidad: String(item.cantidad),
        costo_unitario: item.costo_unitario !== null ? Number(item.costo_unitario).toFixed(2) : ''
      }))
    }
  })
})

async function submit () {
  try {
    const respuesta = await form.submit()
    form.reset()
    proveedor.value = null
    emit('save', respuesta?.data)
  } catch {
    // 422: los errores quedan en form.errors y se ven en cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.oc-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.oc-form__ayuda {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.oc-form__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 16px;
}

.oc-form__grow {
  flex: 1 1 260px;
  min-width: 0;
}

.oc-form__entrega {
  flex: 0 1 220px;
}

.oc-form__textos {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.oc-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.oc-form__labelRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.oc-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

.oc-form__link {
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
.oc-form__control {
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

.oc-form__items {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--app-border-subtle);
}

.oc-form__tablaWrap {
  overflow-x: auto;
}

.oc-form__tabla {
  width: 100%;
  min-width: 640px;
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

.oc-form__producto {
  font-weight: 600;
}

.oc-form__detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.oc-form__stock {
  padding-top: 14px !important;
  white-space: nowrap;
  color: var(--app-ink-2) !important;
}

.oc-form__numero {
  width: 140px;
}

.oc-form__subtotal {
  padding-top: 14px !important;
  font-weight: 600;
}

.oc-form__vacio {
  margin: 0;
  padding: 16px;
  border: 1px dashed var(--app-border-control);
  border-radius: 10px;
  font-size: 12px;
  text-align: center;
  color: var(--app-ink-2);
}

.oc-form__pie {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 24px;
}

.oc-form__totales {
  display: grid;
  grid-template-columns: auto 150px;
  align-items: center;
  gap: 8px 16px;
  margin: 0;
  font-size: 13px;

  dd {
    margin: 0;
    text-align: right;
    color: var(--app-ink);
  }
}

.oc-form__total {
  font-size: 16px;
  font-weight: 700;
  color: var(--app-ink) !important;
}

.oc-form__nota {
  grid-column: 1 / -1;
  font-size: 12px;
  color: var(--app-ink-2) !important;
}

.oc-form__error {
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
