<template>
  <form
    class="movimiento-form"
    novalidate
    @submit.prevent="submit"
  >
    <p class="movimiento-form__ayuda">
      {{ config.ayuda }}
    </p>

    <!-- ── Encabezado del documento ── -->
    <div class="movimiento-form__row">
      <div
        v-if="tipo === 'salida'"
        class="movimiento-form__field movimiento-form__grow"
      >
        <label
          :id="`${uid}-motivo`"
          class="movimiento-form__label"
        >Motivo</label>
        <q-select
          v-model="form.movimiento.motivo"
          :options="MOTIVOS_SALIDA"
          :aria-labelledby="`${uid}-motivo`"
          :error="Boolean(form.errors[`${PATH}.motivo`])"
          :error-message="form.errors[`${PATH}.motivo`]"
          placeholder="Elegí el motivo"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          emit-value
          map-options
          class="movimiento-form__control"
          @update:model-value="form.validate(`${PATH}.motivo`)"
        />
      </div>

      <div
        v-if="tipo === 'traslado'"
        class="movimiento-form__field movimiento-form__grow"
      >
        <label
          :id="`${uid}-destino`"
          class="movimiento-form__label"
        >Sede de destino</label>
        <q-select
          v-model="form.movimiento.sede_destino_id"
          :options="destinos"
          :aria-labelledby="`${uid}-destino`"
          :error="Boolean(form.errors[`${PATH}.sede_destino_id`])"
          :error-message="form.errors[`${PATH}.sede_destino_id`]"
          :placeholder="destinos.length ? 'Elegí la sede' : 'No hay otra sede activa'"
          dense
          outlined
          hide-bottom-space
          no-error-icon
          emit-value
          map-options
          class="movimiento-form__control"
          @update:model-value="form.validate(`${PATH}.sede_destino_id`)"
        >
          <template #prepend>
            <q-icon name="local_shipping" />
          </template>
        </q-select>
      </div>

      <AppTextField
        v-model="form.movimiento.referencia"
        :label="tipo === 'entrada' ? 'Referencia (factura o guía)' : tipo === 'traslado' ? 'Guía de remisión (opcional)' : 'Referencia (opcional)'"
        icon="receipt"
        :placeholder="tipo === 'entrada' ? 'F001-2345' : ''"
        maxlength="60"
        class="movimiento-form__grow"
        :error="form.errors[`${PATH}.referencia`]"
        @change="form.validate(`${PATH}.referencia`)"
      />
    </div>

    <!-- ── Líneas ── -->
    <section class="movimiento-form__lineas">
      <BuscadorVariante
        :excluir="form.movimiento.lineas.map((l) => l.variante_id)"
        @elegir="agregar"
      />

      <p
        v-if="form.errors[`${PATH}.lineas`]"
        class="movimiento-form__error"
        role="alert"
      >
        {{ form.errors[`${PATH}.lineas`] }}
      </p>

      <div
        v-if="form.movimiento.lineas.length"
        class="movimiento-form__tablaWrap"
      >
        <table class="movimiento-form__tabla">
          <thead>
            <tr>
              <th scope="col">
                Presentación
              </th>
              <th
                scope="col"
                class="text-right"
              >
                Stock aquí
              </th>
              <th scope="col">
                {{ tipo === 'ajuste' ? 'Contado' : 'Cantidad' }}
              </th>
              <th
                v-if="tipo === 'entrada'"
                scope="col"
              >
                Costo unit.
              </th>
              <th
                scope="col"
                class="text-right"
              >
                Queda
              </th>
              <th scope="col">
                <span class="sr-only">Quitar</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(linea, i) in form.movimiento.lineas"
              :key="linea.variante_id"
            >
              <td>
                <div class="movimiento-form__variante">
                  <span
                    v-if="linea.variante.color"
                    class="movimiento-form__swatch"
                    :style="{ background: linea.variante.color.hexadecimal }"
                  />
                  <div>
                    <div class="movimiento-form__producto">
                      {{ linea.variante.producto?.nombre }}
                    </div>
                    <div class="movimiento-form__detalle">
                      {{ linea.variante.presentacion }}<template v-if="linea.variante.color"> · {{ linea.variante.color.nombre }}</template> ·
                      <span class="text-mono">{{ linea.variante.sku }}</span>
                    </div>
                  </div>
                </div>
                <p
                  v-if="errorDe(i, 'variante_id')"
                  class="movimiento-form__error"
                >
                  {{ errorDe(i, 'variante_id') }}
                </p>

                <!-- Lote: a cuál entra (entrada / sobrante de un conteo) o de
                     cuál sale (salida; vacío = el que vence primero). -->
                <div
                  v-if="'lote' in linea"
                  class="movimiento-form__lote"
                >
                  <q-input
                    v-model="linea.lote"
                    :aria-label="`Lote de ${linea.variante.sku}`"
                    :placeholder="tipo === 'ajuste' ? 'Lote (si sobra)' : 'Lote'"
                    maxlength="40"
                    dense
                    outlined
                    hide-bottom-space
                    no-error-icon
                    :error="Boolean(errorDe(i, 'lote'))"
                    :error-message="errorDe(i, 'lote')"
                    class="movimiento-form__control movimiento-form__loteCodigo"
                    @change="form.validate(`${PATH}.lineas.${i}.lote`)"
                  />
                  <q-input
                    v-model="linea.vence_at"
                    :aria-label="`Vencimiento del lote de ${linea.variante.sku}`"
                    type="date"
                    dense
                    outlined
                    hide-bottom-space
                    no-error-icon
                    :error="Boolean(errorDe(i, 'vence_at'))"
                    :error-message="errorDe(i, 'vence_at')"
                    class="movimiento-form__control"
                    @change="form.validate(`${PATH}.lineas.${i}.vence_at`)"
                  >
                    <q-tooltip>Vencimiento</q-tooltip>
                  </q-input>
                </div>
                <q-select
                  v-else-if="'lote_id' in linea"
                  v-model="linea.lote_id"
                  :options="lotesDe(linea.variante_id)"
                  :aria-label="`Lote de ${linea.variante.sku}`"
                  placeholder="El que vence primero"
                  clearable
                  dense
                  outlined
                  emit-value
                  map-options
                  hide-bottom-space
                  :error="Boolean(errorDe(i, 'lote_id'))"
                  :error-message="errorDe(i, 'lote_id')"
                  class="movimiento-form__control movimiento-form__lote"
                />
              </td>

              <td class="text-right text-mono">
                {{ formatearCantidad(linea.variante.stock) }} {{ linea.variante.unidad?.abreviatura }}
              </td>

              <td class="movimiento-form__numero">
                <q-input
                  v-if="tipo === 'ajuste'"
                  v-model="linea.stock_real"
                  :aria-label="`Stock contado de ${linea.variante.sku}`"
                  type="number"
                  min="0"
                  :step="paso(linea)"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorDe(i, 'stock_real'))"
                  :error-message="errorDe(i, 'stock_real')"
                  class="movimiento-form__control"
                  @change="form.validate(`${PATH}.lineas.${i}.stock_real`)"
                />
                <q-input
                  v-else
                  v-model="linea.cantidad"
                  :aria-label="`Cantidad de ${linea.variante.sku}`"
                  type="number"
                  min="0"
                  :step="paso(linea)"
                  dense
                  outlined
                  hide-bottom-space
                  no-error-icon
                  :error="Boolean(errorDe(i, 'cantidad'))"
                  :error-message="errorDe(i, 'cantidad')"
                  class="movimiento-form__control"
                  @change="form.validate(`${PATH}.lineas.${i}.cantidad`)"
                />
              </td>

              <td
                v-if="tipo === 'entrada'"
                class="movimiento-form__numero"
              >
                <q-input
                  v-model="linea.costo_unitario"
                  :aria-label="`Costo unitario de ${linea.variante.sku}`"
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
                  class="movimiento-form__control"
                  @change="form.validate(`${PATH}.lineas.${i}.costo_unitario`)"
                />
              </td>

              <!-- Vista previa: cómo queda el stock si se guarda. -->
              <td
                :class="['text-right', 'text-mono', 'movimiento-form__queda', claseDiferencia(linea)]"
              >
                {{ formatearCantidad(stockResultante(linea)) ?? '—' }}
              </td>

              <td class="text-right">
                <q-btn
                  flat
                  dense
                  round
                  icon="close"
                  size="sm"
                  color="grey-7"
                  :aria-label="`Quitar ${linea.variante.sku}`"
                  @click="quitar(i)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p
        v-else
        class="movimiento-form__vacio"
      >
        Buscá las presentaciones arriba para agregarlas al documento.
      </p>

      <div
        v-if="tipo === 'entrada' && form.movimiento.lineas.length"
        class="movimiento-form__total"
      >
        <span>{{ form.movimiento.lineas.length }} {{ form.movimiento.lineas.length === 1 ? 'presentación' : 'presentaciones' }}</span>
        <strong class="text-mono">{{ formatearPrecio(totalCompra) || formatearPrecio(0) }}</strong>
      </div>
    </section>

    <AppTextField
      v-model="form.movimiento.observacion"
      label="Observación (opcional)"
      type="textarea"
      autogrow
      maxlength="500"
      :error="form.errors[`${PATH}.observacion`]"
      @change="form.validate(`${PATH}.observacion`)"
    />

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { computed, onMounted, ref, useId } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppTextField from '@/components/AppTextField.vue'
import InventarioService from '@/services/InventarioService'
import SedeService from '@/services/SedeService'
import { useUserStore } from '@/stores/user-store'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'
import BuscadorVariante from './BuscadorVariante.vue'
import formMovimiento, { nuevaLinea } from './FormMovimiento'
import { MOTIVOS_SALIDA, TIPOS } from './constantes'

const PATH = 'movimiento'

const props = defineProps({
  tipo: {
    type: String,
    required: true,
    validator: (valor) => valor in TIPOS
  },
  // Presentaciones con las que arranca (desde la ficha de un producto), en la
  // forma de /inventario/variantes. Las que se dejen sin cantidad no viajan.
  iniciales: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['save'])

const uid = `movimiento-${useId()}`
const config = computed(() => TIPOS[props.tipo])

const form = useForm('post', `api/inventario/${TIPOS[props.tipo].endpoint}`, () => formMovimiento(props.tipo))

// ── Traslado: las otras sedes activas ──
const userStore = useUserStore()
const sedes = ref([])
const destinos = computed(() => sedes.value
  .filter((s) => s.id !== userStore.sedeId)
  .map((s) => ({ value: s.id, label: s.nombre })))

onMounted(async () => {
  if (props.tipo !== 'traslado') return
  sedes.value = await SedeService.activas()
  if (destinos.value.length === 1) form.movimiento.sede_destino_id = destinos.value[0].value
})

// Las unidades fraccionables (kg, m) aceptan decimales; bolsas y baldes, no.
function paso (linea) {
  return linea.variante.unidad?.fraccionable ? '0.001' : '1'
}

function errorDe (i, campo) {
  return form.errors[`${PATH}.lineas.${i}.${campo}`]
}

function agregar (variante) {
  form.movimiento.lineas.push(nuevaLinea(props.tipo, variante))
  if (props.tipo === 'salida' && variante.maneja_lotes) cargarLotes(variante.id)
}

// ── Lotes para elegir en una salida (por presentación) ──
const lotesPorVariante = ref({})

async function cargarLotes (varianteId) {
  if (lotesPorVariante.value[varianteId]) return
  const { data } = await InventarioService.lotes({ params: { variante_id: varianteId, rowsPerPage: 0 } })
  lotesPorVariante.value = { ...lotesPorVariante.value, [varianteId]: data }
}

function lotesDe (varianteId) {
  return (lotesPorVariante.value[varianteId] ?? []).map((l) => ({
    value: l.id,
    label: `${l.codigo} · ${formatearCantidad(l.cantidad)}${l.vence_at ? ` · vence ${formatearFechaCorta(l.vence_at)}` : ''}${l.estado === 'vencido' ? ' (VENCIDO)' : ''}`
  }))
}

function formatearFechaCorta (iso) {
  const [anio, mes, dia] = iso.split('-')
  return `${dia}/${mes}/${anio}`
}

props.iniciales.forEach(agregar)

function quitar (i) {
  form.movimiento.lineas.splice(i, 1)
}

// null mientras el número no sea válido.
function numero (valor) {
  const n = Number(valor)
  return valor !== '' && valor !== null && Number.isFinite(n) ? n : null
}

// Sin el ruido de los decimales flotantes (0.1 + 0.2).
function redondear (n) {
  return Math.round(n * 1000) / 1000
}

function stockResultante (linea) {
  const stock = linea.variante.stock

  if (props.tipo === 'ajuste') return numero(linea.stock_real)

  const cantidad = numero(linea.cantidad)
  if (cantidad === null) return null
  return redondear(props.tipo === 'entrada' ? stock + cantidad : stock - cantidad)
}

// Rojo si una salida deja el stock negativo; resaltado si el ajuste cambia algo.
function claseDiferencia (linea) {
  const queda = stockResultante(linea)
  if (queda === null) return ''
  if (queda < 0) return 'movimiento-form__queda--negativo'
  return queda !== linea.variante.stock ? 'movimiento-form__queda--cambia' : ''
}

const totalCompra = computed(() => form.movimiento.lineas.reduce((suma, l) => {
  const cantidad = numero(l.cantidad) ?? 0
  const costo = Number(l.costo_unitario)
  return suma + (Number.isFinite(costo) ? cantidad * costo : 0)
}, 0))

// Las líneas precargadas que quedaron vacías no son un error: esa
// presentación simplemente no entró (o no salió) esta vez.
function descartarVacias () {
  if (!props.iniciales.length || props.tipo === 'ajuste') return
  const precargadas = new Set(props.iniciales.map((v) => v.id))
  form.movimiento.lineas = form.movimiento.lineas.filter((l) =>
    !(precargadas.has(l.variante_id) && (l.cantidad === '' || l.cantidad === null)))
}

async function submit () {
  descartarVacias()
  try {
    const respuesta = await form.submit()
    form.reset()
    emit('save', respuesta?.data?.data ?? [])
  } catch {
    // 422: los errores quedan en form.errors y se ven en cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.movimiento-form__lote {
  display: flex;
  gap: 6px;
  margin-top: 6px;
  max-width: 340px;
}

.movimiento-form__loteCodigo {
  flex: 1;
}

.movimiento-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.movimiento-form__ayuda {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.movimiento-form__row {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}

.movimiento-form__grow {
  flex: 1 1 220px;
  min-width: 0;
}

.movimiento-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.movimiento-form__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

// Mismo radio, borde y foco que AppTextField.
.movimiento-form__control {
  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }

  :deep(.q-field__control):hover:before {
    border-color: var(--app-border-control-hover);
  }

  &.q-field--focused :deep(.q-field__control) {
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }
}

.movimiento-form__lineas {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--app-border-subtle);
}

.movimiento-form__tablaWrap {
  overflow-x: auto;
}

.movimiento-form__tabla {
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

.movimiento-form__variante {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.movimiento-form__swatch {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
  margin-top: 2px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 4px;
}

.movimiento-form__producto {
  font-weight: 600;
}

.movimiento-form__detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.movimiento-form__numero {
  width: 130px;
}

.movimiento-form__queda {
  padding-top: 14px !important;
  font-weight: 600;

  &--cambia {
    color: $primary;
  }

  &--negativo {
    color: var(--q-negative);
  }
}

.movimiento-form__total {
  display: flex;
  justify-content: flex-end;
  align-items: baseline;
  gap: 16px;
  font-size: 13px;
  color: var(--app-ink-2);

  strong {
    font-size: 16px;
    color: var(--app-ink);
  }
}

.movimiento-form__vacio {
  margin: 0;
  padding: 16px;
  border: 1px dashed var(--app-border-control);
  border-radius: 10px;
  font-size: 12px;
  text-align: center;
  color: var(--app-ink-2);
}

.movimiento-form__error {
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
