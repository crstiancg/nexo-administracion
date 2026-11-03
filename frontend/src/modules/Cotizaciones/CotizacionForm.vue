<template>
  <form
    class="cotizacion-form"
    novalidate
    @submit.prevent="submit"
  >
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
        >Válida hasta <span class="cotizacion-form__requerido">*</span></label>
        <q-input
          :id="`${uid}-validez`"
          v-model="form.cotizacion.valida_hasta"
          type="date"
          :min="hoy"
          required
          dense
          outlined
          hide-bottom-space
          no-error-icon
          :error="Boolean(form.errors[`${PATH}.valida_hasta`])"
          :error-message="form.errors[`${PATH}.valida_hasta`]"
          class="cotizacion-form__control"
          @change="form.validate(`${PATH}.valida_hasta`)"
        />
      </div>
    </div>

    <!-- ── Ítems ── -->
    <section class="cotizacion-form__items">
      <BuscadorVariante
        label="Agregar producto: SKU o nombre"
        :excluir="form.cotizacion.items.map((i) => i.variante_id)"
        @elegir="agregar"
      />

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
              :key="item.variante_id"
            >
              <td>
                <div class="cotizacion-form__variante">
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
                  v-if="errorDe(i, 'variante_id')"
                  class="cotizacion-form__error"
                >
                  {{ errorDe(i, 'variante_id') }}
                </p>
              </td>

              <td class="cotizacion-form__numero">
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
        class="cotizacion-form__vacio"
      >
        Buscá los productos arriba para agregarlos a la cotización.
      </p>
    </section>

    <!-- ── Totales ── -->
    <div class="cotizacion-form__pie">
      <div class="cotizacion-form__grow cotizacion-form__textos">
        <AppTextField
          v-model="form.cotizacion.condiciones"
          label="Condiciones (salen impresas)"
          type="textarea"
          autogrow
          maxlength="500"
          placeholder="Pago: 50% adelanto. Entrega en obra en 48 h. Precios incluyen IGV."
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
import AppTextField from '@/components/AppTextField.vue'
import ClientesForm from '@/modules/Clientes/ClientesForm.vue'
import BuscadorVariante from '@/modules/Inventario/BuscadorVariante.vue'
import CotizacionService from '@/services/CotizacionService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import { desglosarIgv, ETIQUETA_IGV } from '@/utils/igv'
import BuscadorCliente from '@/modules/Pedidos/BuscadorCliente.vue'
import { nuevoItem, reprecio } from '@/modules/Pedidos/FormPedido'
import formCotizacion from './FormCotizacion'
import { hoyLocal } from './constantes'

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

const form = props.id
  ? useForm('put', `api/cotizaciones/${props.id}`, formCotizacion)
  : useForm('post', 'api/cotizaciones', formCotizacion)

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

function agregar (variante) {
  form.cotizacion.items.push(nuevoItem(variante, cliente.value))
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
  return numero(item.cantidad) > item.variante.stock
}

// Vista previa: el total que vale es el que calcula el backend.
const subtotal = computed(() => form.cotizacion.items.reduce((suma, item) => suma + subtotalDe(item), 0))
const total = computed(() => Math.max(0, subtotal.value - numero(form.cotizacion.descuento)))

// ── Carga ──
onMounted(async () => {
  if (!props.id) return

  const cotizacion = await CotizacionService.get(props.id)
  cliente.value = cotizacion.cliente
  form.setData({
    [PATH]: {
      cliente_id: cotizacion.cliente_id,
      // Una vencida se edita para renovarla: la validez queda vacía para
      // poner la nueva.
      valida_hasta: cotizacion.valida_hasta < hoy ? '' : cotizacion.valida_hasta,
      condiciones: cotizacion.condiciones ?? '',
      descuento: Number(cotizacion.descuento) ? cotizacion.descuento : '',
      observacion: cotizacion.observacion ?? '',
      items: cotizacion.items.map((item) => ({
        variante_id: item.variante_id,
        variante: item.variante,
        cantidad: String(item.cantidad),
        precio_unitario: item.precio_unitario
      }))
    }
  })
})

// Sin cliente se puede ("cotización sin nombre"), pero se avisa antes: a
// veces es un olvido. La validez es obligatoria.
function submit () {
  if (!form.cotizacion.valida_hasta) {
    form.setErrors({ ...form.errors, [`${PATH}.valida_hasta`]: 'Poné hasta cuándo vale la cotización.' })
    return
  }

  if (form.cotizacion.cliente_id) {
    guardar()
    return
  }

  $q.notify({
    message: 'Vas a guardar la cotización sin cliente',
    caption: 'Sale impresa sin nombre. Si es para alguien en particular, elegilo o crealo con “+ Nuevo cliente”.',
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
  color: $primary;
  cursor: pointer;

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }
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
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
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
