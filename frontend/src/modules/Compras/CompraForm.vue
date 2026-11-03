<template>
  <form
    class="compra-form"
    novalidate
    @submit.prevent="submit"
  >
    <p class="compra-form__ayuda">
      Al registrarla, la mercadería entra al stock de <strong>{{ userStore.sede?.nombre ?? 'tu sede' }}</strong> y su costo
      actualiza el costo promedio. Si te equivocás, se anula y se carga de nuevo.
    </p>

    <!-- ── Proveedor y documento ── -->
    <div class="compra-form__row">
      <div class="compra-form__field compra-form__grow">
        <div class="compra-form__labelRow">
          <label
            :id="`${uid}-proveedor`"
            class="compra-form__label"
          >Proveedor</label>
          <button
            v-if="userStore.hasPermission('proveedores.store')"
            type="button"
            class="compra-form__link"
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
          class="compra-form__error"
        >
          {{ form.errors[`${PATH}.proveedor_id`] }}
        </p>
      </div>

      <div class="compra-form__field compra-form__fecha">
        <label
          :for="`${uid}-fecha`"
          class="compra-form__label"
        >Fecha</label>
        <q-input
          :id="`${uid}-fecha`"
          v-model="form.compra.fecha"
          type="date"
          :max="hoy"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          :error="Boolean(form.errors[`${PATH}.fecha`])"
          :error-message="form.errors[`${PATH}.fecha`]"
          class="compra-form__control"
          @change="form.validate(`${PATH}.fecha`)"
        />
      </div>
    </div>

    <div class="compra-form__row">
      <div class="compra-form__field compra-form__tipo">
        <label
          :id="`${uid}-tipo`"
          class="compra-form__label"
        >Documento</label>
        <q-select
          v-model="form.compra.tipo_documento"
          :options="TIPOS_DOCUMENTO"
          :aria-labelledby="`${uid}-tipo`"
          dense
          outlined
          hide-bottom-space
          emit-value
          map-options
          class="compra-form__control"
          @update:model-value="form.validate(`${PATH}.numero_documento`)"
        />
      </div>

      <AppTextField
        v-model="form.compra.numero_documento"
        label="Número"
        icon="receipt"
        placeholder="F001-2345"
        maxlength="30"
        class="compra-form__grow"
        :error="form.errors[`${PATH}.numero_documento`]"
        @change="form.validate(`${PATH}.numero_documento`)"
      />
    </div>

    <!-- ── Ítems ── -->
    <section class="compra-form__items">
      <BuscadorVariante
        label="Agregar producto: SKU o nombre"
        :excluir="form.compra.items.map((i) => i.variante_id)"
        @elegir="agregar"
      />

      <p
        v-if="form.errors[`${PATH}.items`]"
        class="compra-form__error"
        role="alert"
      >
        {{ form.errors[`${PATH}.items`] }}
      </p>

      <div
        v-if="form.compra.items.length"
        class="compra-form__tablaWrap"
      >
        <table class="compra-form__tabla">
          <thead>
            <tr>
              <th scope="col">
                Producto
              </th>
              <th scope="col">
                Cantidad
              </th>
              <th scope="col">
                Costo unit.
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
              v-for="(item, i) in form.compra.items"
              :key="item.variante_id"
            >
              <td>
                <div class="compra-form__producto">
                  {{ item.variante.producto?.nombre }}
                </div>
                <div class="compra-form__detalle">
                  {{ item.variante.presentacion }}<template v-if="item.variante.color">
                    · {{ item.variante.color.nombre }}
                  </template> ·
                  <span class="text-mono">{{ item.variante.sku }}</span>
                </div>
                <p
                  v-if="errorDe(i, 'variante_id')"
                  class="compra-form__error"
                >
                  {{ errorDe(i, 'variante_id') }}
                </p>

                <!-- Productos con lotes: el lote y su vencimiento. -->
                <div
                  v-if="item.variante.maneja_lotes"
                  class="compra-form__lote"
                >
                  <q-input
                    v-model="item.lote"
                    :aria-label="`Lote de ${item.variante.sku}`"
                    placeholder="Lote"
                    maxlength="40"
                    dense
                    outlined
                    hide-bottom-space
                    no-error-icon
                    :error="Boolean(errorDe(i, 'lote'))"
                    :error-message="errorDe(i, 'lote')"
                    class="compra-form__control compra-form__loteCodigo"
                    @change="form.validate(`${PATH}.items.${i}.lote`)"
                  />
                  <q-input
                    v-model="item.vence_at"
                    :aria-label="`Vencimiento del lote de ${item.variante.sku}`"
                    type="date"
                    :min="hoy"
                    dense
                    outlined
                    hide-bottom-space
                    no-error-icon
                    :error="Boolean(errorDe(i, 'vence_at'))"
                    :error-message="errorDe(i, 'vence_at')"
                    class="compra-form__control"
                    @change="form.validate(`${PATH}.items.${i}.vence_at`)"
                  >
                    <q-tooltip>Vencimiento</q-tooltip>
                  </q-input>
                </div>
              </td>

              <td class="compra-form__numero">
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
                  class="compra-form__control"
                  @change="form.validate(`${PATH}.items.${i}.cantidad`)"
                />
              </td>

              <td class="compra-form__numero">
                <q-input
                  v-model="item.costo_unitario"
                  :aria-label="`Costo unitario de ${item.variante.sku}`"
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
                  class="compra-form__control"
                  @change="form.validate(`${PATH}.items.${i}.costo_unitario`)"
                />
              </td>

              <td class="text-right text-mono compra-form__subtotal">
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
        class="compra-form__vacio"
      >
        Buscá los productos arriba para agregarlos a la compra.
      </p>
    </section>

    <div class="compra-form__pie">
      <AppTextField
        v-model="form.compra.observacion"
        label="Observación (opcional)"
        type="textarea"
        autogrow
        maxlength="500"
        class="compra-form__grow"
        :error="form.errors[`${PATH}.observacion`]"
      />

      <dl class="compra-form__totales">
        <dt>Op. gravada</dt>
        <dd class="text-mono">
          {{ formatearPrecio(desglosarIgv(total).opGravada) }}
        </dd>
        <dt>{{ ETIQUETA_IGV }}</dt>
        <dd class="text-mono">
          {{ formatearPrecio(desglosarIgv(total).igv) }}
        </dd>
        <dt class="compra-form__total">
          Total
        </dt>
        <dd class="compra-form__total text-mono">
          {{ formatearPrecio(total) }}
        </dd>
      </dl>
    </div>

    <button
      type="submit"
      hidden
    />

    <!-- Alta rápida sin salir de la compra. -->
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
import { computed, ref, useId, watch } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppTextField from '@/components/AppTextField.vue'
import BuscadorVariante from '@/modules/Inventario/BuscadorVariante.vue'
import ProveedoresForm from '@/modules/Proveedores/ProveedoresForm.vue'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import { desglosarIgv, ETIQUETA_IGV } from '@/utils/igv'
import BuscadorProveedor from './BuscadorProveedor.vue'
import { TIPOS_DOCUMENTO } from './constantes'

const PATH = 'compra'

const props = defineProps({
  // Al recibir una orden de compra: proveedor e ítems ya cargados.
  orden: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['save'])

const userStore = useUserStore()
const uid = `compra-${useId()}`

// Fecha local (no UTC): a las 9 p. m. en Lima, toISOString ya es "mañana".
const ahora = new Date()
const hoy = `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}-${String(ahora.getDate()).padStart(2, '0')}`

// La clave `compra` es la misma que valida StoreCompraRequest. Función y no
// objeto: useForm guarda estos datos como "originales" para el reset().
const form = useForm('post', 'api/compras', () => ({
  compra: {
    proveedor_id: null,
    tipo_documento: 'factura',
    numero_documento: '',
    fecha: hoy,
    observacion: '',
    orden_compra_id: null,
    items: []
  }
}))

// ── Proveedor ──
// El objeto completo para mostrarlo; al backend viaja sólo el id.
const proveedor = ref(null)
watch(proveedor, (valor) => { form.compra.proveedor_id = valor?.id ?? null })

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

// Se propone el costo promedio actual: casi siempre la compra se repite.
// `variante` viaja sólo para mostrar (el backend usa variante_id).
function agregar (variante) {
  form.compra.items.push({
    variante_id: variante.id,
    variante,
    cantidad: '',
    costo_unitario: variante.costo_promedio !== null ? Number(variante.costo_promedio).toFixed(2) : '',
    ...(variante.maneja_lotes ? { lote: '', vence_at: '' } : {})
  })
}

function quitar (i) {
  form.compra.items.splice(i, 1)
}

// Recibir una orden: lo pedido, con el costo pactado (o el promedio). Se
// ajusta a lo que llegó de verdad; el lote y el vencimiento, del envase.
if (props.orden) {
  proveedor.value = props.orden.proveedor
  form.compra.orden_compra_id = props.orden.id
  form.compra.observacion = `Recepción del pedido de compra ${props.orden.codigo}`
  for (const item of props.orden.items) {
    agregar(item.variante)
    const linea = form.compra.items[form.compra.items.length - 1]
    linea.cantidad = String(item.cantidad)
    if (item.costo_unitario !== null) linea.costo_unitario = Number(item.costo_unitario).toFixed(2)
  }
}

function numero (valor) {
  const n = Number(valor)
  return valor !== '' && Number.isFinite(n) ? n : 0
}

function subtotalDe (item) {
  return Math.round(numero(item.cantidad) * numero(item.costo_unitario) * 100) / 100
}

// Vista previa: el total que vale es el que calcula el backend.
const total = computed(() => form.compra.items.reduce((suma, item) => suma + subtotalDe(item), 0))

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
.compra-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.compra-form__ayuda {
  margin: 0;
  font-size: 12.5px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.compra-form__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 16px;
}

.compra-form__grow {
  flex: 1 1 260px;
  min-width: 0;
}

.compra-form__fecha,
.compra-form__tipo {
  flex: 0 1 200px;
}

.compra-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.compra-form__labelRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.compra-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

.compra-form__link {
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
.compra-form__control {
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

.compra-form__items {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--app-border-subtle);
}

.compra-form__tablaWrap {
  overflow-x: auto;
}

.compra-form__tabla {
  width: 100%;
  min-width: 620px;
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

.compra-form__producto {
  font-weight: 600;
}

.compra-form__detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.compra-form__lote {
  display: flex;
  gap: 6px;
  max-width: 340px;
  margin-top: 6px;
}

.compra-form__loteCodigo {
  flex: 1;
}

.compra-form__numero {
  width: 130px;
}

.compra-form__subtotal {
  padding-top: 14px !important;
  font-weight: 600;
}

.compra-form__vacio {
  margin: 0;
  padding: 16px;
  border: 1px dashed var(--app-border-control);
  border-radius: 10px;
  font-size: 12px;
  text-align: center;
  color: var(--app-ink-2);
}

.compra-form__pie {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 24px;
}

.compra-form__totales {
  display: grid;
  grid-template-columns: auto 150px;
  align-items: center;
  gap: 8px 16px;
  margin: 0;

  dd {
    margin: 0;
    text-align: right;
  }
}

.compra-form__total {
  font-size: 16px;
  font-weight: 700;
  color: var(--app-ink);
}

.compra-form__error {
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
