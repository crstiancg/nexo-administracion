<template>
  <form
    class="orden-form"
    novalidate
    @submit.prevent="submit"
  >
    <!-- Desde una cotización aceptada: llega prellenada y queda convertida. -->
    <p
      v-if="cotizacion"
      class="orden-form__desde"
    >
      <q-icon
        name="request_quote"
        size="18px"
      />
      <span>
        Desde la cotización <strong class="text-mono">{{ cotizacion.codigo }}</strong>: completa los datos del equipo.
        Al registrar la orden, la cotización queda convertida.
        <template v-if="Number(cotizacion.descuento)">
          El descuento cotizado ({{ formatearPrecio(cotizacion.descuento) }}) no se pasa: ajústalo en la mano de obra si corresponde.
        </template>
      </span>
    </p>

    <!-- ── Cliente y técnico ── -->
    <section class="orden-form__seccion">
      <h3 class="orden-form__titulo">
        <q-icon name="person" /> Cliente
      </h3>
      <div class="orden-form__row">
        <div class="orden-form__field orden-form__grow">
          <div class="orden-form__labelRow">
            <label
              :id="`${uid}-cliente`"
              class="orden-form__label"
            >Dueño del equipo <span class="orden-form__requerido">*</span></label>
            <button
              v-if="userStore.hasPermission('clientes.store')"
              type="button"
              class="orden-form__link"
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
            class="orden-form__error"
          >
            {{ form.errors[`${PATH}.cliente_id`] }}
          </p>
          <p
            v-else-if="cliente && !cliente.telefono"
            class="orden-form__aviso"
          >
            Sin teléfono: no se le podrá avisar por WhatsApp.
          </p>
        </div>

        <div class="orden-form__field orden-form__medio">
          <label
            :for="`${uid}-tecnico`"
            class="orden-form__label"
          >Técnico a cargo</label>
          <q-select
            :id="`${uid}-tecnico`"
            v-model="form.orden.tecnico_id"
            :options="tecnicos"
            option-value="id"
            option-label="name"
            emit-value
            map-options
            clearable
            dense
            outlined
            placeholder="Sin asignar"
            class="orden-form__control"
          />
        </div>
      </div>
    </section>

    <!-- ── Equipo ── -->
    <section class="orden-form__seccion">
      <h3 class="orden-form__titulo">
        <q-icon name="devices" /> Equipo
      </h3>
      <div class="orden-form__row">
        <div class="orden-form__field orden-form__medio">
          <label
            :for="`${uid}-tipo`"
            class="orden-form__label"
          >Tipo <span class="orden-form__requerido">*</span></label>
          <q-select
            :id="`${uid}-tipo`"
            v-model="form.orden.tipo_equipo_id"
            :options="tipos"
            option-value="id"
            option-label="nombre"
            emit-value
            map-options
            dense
            outlined
            hide-bottom-space
            :error="Boolean(form.errors[`${PATH}.tipo_equipo_id`])"
            :error-message="form.errors[`${PATH}.tipo_equipo_id`]"
            class="orden-form__control"
          />
        </div>
        <div class="orden-form__field orden-form__medio">
          <label
            :for="`${uid}-marca`"
            class="orden-form__label"
          >Marca</label>
          <q-select
            :id="`${uid}-marca`"
            v-model="form.orden.marca_id"
            :options="marcasFiltradas"
            option-value="id"
            option-label="nombre"
            emit-value
            map-options
            use-input
            input-debounce="0"
            clearable
            dense
            outlined
            class="orden-form__control"
            @filter="filtrarMarcas"
          />
        </div>
        <AppTextField
          v-model="form.orden.modelo"
          label="Modelo"
          placeholder="IdeaPad 3, Galaxy A14…"
          maxlength="80"
          class="orden-form__medio"
          :error="form.errors[`${PATH}.modelo`]"
        />
        <AppTextField
          v-model="form.orden.serie"
          label="Serie / IMEI"
          maxlength="60"
          class="orden-form__medio"
          :error="form.errors[`${PATH}.serie`]"
        />
      </div>

      <AppTextField
        v-model="form.orden.accesorios"
        label="Accesorios que deja"
        placeholder="Cargador, funda, mochila…"
        maxlength="250"
        :error="form.errors[`${PATH}.accesorios`]"
      />

      <div class="orden-form__row orden-form__row--centro">
        <div
          class="orden-form__seguridad"
          role="radiogroup"
          aria-label="Bloqueo del equipo"
        >
          <span class="orden-form__label">Bloqueo</span>
          <q-radio
            v-for="opcion in SEGURIDAD"
            :key="opcion.value"
            v-model="form.orden.seguridad_tipo"
            :val="opcion.value"
            :label="opcion.label"
            dense
            color="primary"
          />
        </div>
        <AppTextField
          v-if="form.orden.seguridad_tipo !== 'ninguno'"
          v-model="form.orden.seguridad_valor"
          :label="form.orden.seguridad_tipo === 'patron' ? 'Patrón (ej. 1-4-7-8-9)' : 'Clave'"
          icon="key"
          maxlength="100"
          class="orden-form__grow"
          :error="form.errors[`${PATH}.seguridad_valor`]"
          @change="form.validate(`${PATH}.seguridad_valor`)"
        />
      </div>
    </section>

    <!-- ── Revisión al recibir ── -->
    <section
      v-if="Object.keys(form.orden.checklist).length"
      class="orden-form__seccion"
    >
      <h3 class="orden-form__titulo">
        <q-icon name="fact_check" /> Cómo llega el equipo
      </h3>
      <div class="orden-form__checklist">
        <q-toggle
          v-for="(valor, punto) in form.orden.checklist"
          :key="punto"
          v-model="form.orden.checklist[punto]"
          :label="punto"
          color="positive"
          dense
        />
      </div>
    </section>

    <!-- ── Problema ── -->
    <section class="orden-form__seccion">
      <h3 class="orden-form__titulo">
        <q-icon name="report_problem" /> Problema
      </h3>
      <AppTextField
        v-model="form.orden.falla"
        label="Falla que reporta el cliente *"
        type="textarea"
        autogrow
        maxlength="1000"
        :error="form.errors[`${PATH}.falla`]"
        @change="form.validate(`${PATH}.falla`)"
      />
      <AppTextField
        v-model="form.orden.diagnostico"
        label="Diagnóstico / trabajo a realizar"
        type="textarea"
        autogrow
        maxlength="2000"
        :error="form.errors[`${PATH}.diagnostico`]"
      />
      <div class="orden-form__row orden-form__row--centro">
        <div class="orden-form__field orden-form__medio">
          <label
            :for="`${uid}-garantia`"
            class="orden-form__label"
          >Garantía del trabajo</label>
          <q-select
            :id="`${uid}-garantia`"
            v-model="form.orden.garantia_dias"
            :options="GARANTIAS"
            emit-value
            map-options
            dense
            outlined
            class="orden-form__control"
          />
        </div>
        <q-toggle
          v-model="form.orden.es_garantia"
          label="Ingresa por garantía de un trabajo anterior"
          color="primary"
        />
      </div>
      <AppTextField
        v-model="form.orden.observacion"
        label="Nota interna (no la ve el cliente)"
        maxlength="500"
        :error="form.errors[`${PATH}.observacion`]"
      />
    </section>

    <!-- ── Repuestos y cobro ── -->
    <section class="orden-form__seccion">
      <h3 class="orden-form__titulo">
        <q-icon name="payments" /> Repuestos y presupuesto
      </h3>
      <BuscadorVariante
        label="Agregar repuesto del inventario: SKU o nombre"
        :excluir="form.orden.repuestos.map((r) => r.variante_id)"
        @elegir="agregar"
      />

      <div
        v-if="form.orden.repuestos.length"
        class="orden-form__tablaWrap"
      >
        <table class="orden-form__tabla">
          <thead>
            <tr>
              <th scope="col">
                Repuesto
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
              v-for="(item, i) in form.orden.repuestos"
              :key="item.variante_id"
            >
              <td>
                <div class="orden-form__producto">
                  {{ item.variante.producto?.nombre }}
                </div>
                <div class="orden-form__detalle">
                  {{ item.variante.presentacion }} · <span class="text-mono">{{ item.variante.sku }}</span>
                </div>
                <p
                  v-if="errorDe(i, 'variante_id')"
                  class="orden-form__error"
                >
                  {{ errorDe(i, 'variante_id') }}
                </p>
              </td>
              <td class="orden-form__numero">
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
                  class="orden-form__control"
                />
                <!-- Aviso: el stock se descuenta recién al entregar. -->
                <p
                  v-if="!errorDe(i, 'cantidad') && numero(item.cantidad) > Number(item.variante.stock ?? 0)"
                  class="orden-form__aviso"
                >
                  Hay {{ item.variante.stock ?? 0 }} en tu sede
                </p>
              </td>
              <td class="orden-form__numero">
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
                  class="orden-form__control"
                />
              </td>
              <td class="text-right text-mono orden-form__subtotal">
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
                  @click="form.orden.repuestos.splice(i, 1)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="orden-form__pie">
        <div class="orden-form__grow orden-form__anticipo">
          <template v-if="!props.id">
            <span class="orden-form__label">Adelanto (opcional, entra a la caja abierta)</span>
            <div class="orden-form__row">
              <q-select
                v-model="form.orden.anticipo.metodo"
                :options="METODOS"
                emit-value
                map-options
                dense
                outlined
                aria-label="Método del adelanto"
                class="orden-form__control orden-form__medio"
              />
              <AppTextField
                v-model="form.orden.anticipo.monto"
                label="Monto"
                type="number"
                min="0"
                step="0.01"
                class="orden-form__medio"
                :error="form.errors[`${PATH}.anticipo.monto`]"
              />
              <AppTextField
                v-if="CON_OPERACION.includes(form.orden.anticipo.metodo) && numero(form.orden.anticipo.monto) > 0"
                v-model="form.orden.anticipo.referencia"
                label="N° de operación"
                maxlength="40"
                class="orden-form__medio"
                :error="form.errors[`${PATH}.anticipo.referencia`]"
              />
            </div>
          </template>
          <p
            v-else
            class="orden-form__nota"
          >
            Los abonos se registran desde el detalle de la orden.
          </p>
        </div>

        <dl class="orden-form__totales">
          <dt>Repuestos</dt>
          <dd class="text-mono">
            {{ formatearPrecio(totalRepuestos) }}
          </dd>
          <dt>
            <label :for="`${uid}-mano`">Mano de obra</label>
          </dt>
          <dd>
            <q-input
              :id="`${uid}-mano`"
              v-model="form.orden.mano_obra"
              type="number"
              min="0"
              step="0.01"
              prefix="S/"
              placeholder="0.00"
              dense
              outlined
              hide-bottom-space
              no-error-icon
              :error="Boolean(form.errors[`${PATH}.mano_obra`])"
              :error-message="form.errors[`${PATH}.mano_obra`]"
              class="orden-form__control"
            />
          </dd>
          <dt class="orden-form__total">
            Total
          </dt>
          <dd class="orden-form__total text-mono">
            {{ formatearPrecio(total) }}
          </dd>
        </dl>
      </div>

      <p
        v-if="errorGeneral"
        class="orden-form__error"
        role="alert"
      >
        {{ errorGeneral }}
      </p>
    </section>

    <button
      type="submit"
      hidden
    />

    <!-- Alta rápida sin salir de la orden. -->
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
import { CON_OPERACION, METODOS } from '@/modules/Caja/constantes'
import ClientesForm from '@/modules/Clientes/ClientesForm.vue'
import BuscadorVariante from '@/modules/Inventario/BuscadorVariante.vue'
import BuscadorCliente from '@/modules/Pedidos/BuscadorCliente.vue'
import { nuevoItem } from '@/modules/Pedidos/FormPedido'
import MarcaService from '@/services/MarcaService'
import CotizacionService from '@/services/CotizacionService'
import OrdenServicioService from '@/services/OrdenServicioService'
import TipoEquipoService from '@/services/TipoEquipoService'
import { useUserStore } from '@/stores/user-store'
import { formatearPrecio } from '@/utils/moneda'
import formOrden, { checklistPara } from './FormOrden'
import { GARANTIAS, SEGURIDAD } from './constantes'

const PATH = 'orden'

const props = defineProps({
  // null = recibir un equipo; con id = editar (mientras no se entregue).
  id: {
    type: Number,
    default: null
  },
  // Al recibir: la cotización aceptada con la que se prellena la orden.
  cotizacionId: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['save'])

const userStore = useUserStore()
const uid = `orden-${useId()}`

const form = props.id
  ? useForm('put', `api/ordenes-servicio/${props.id}`, formOrden)
  : useForm('post', 'api/ordenes-servicio', formOrden)

// ── Catálogos ──
const tipos = ref([])
const marcas = ref([])
const marcasFiltradas = ref([])
const tecnicos = ref([])

function filtrarMarcas (valor, update) {
  const t = valor.trim().toLowerCase()
  update(() => {
    marcasFiltradas.value = t ? marcas.value.filter((m) => m.nombre.toLowerCase().includes(t)) : marcas.value
  })
}

// Al elegir el tipo aparece su lista de revisión.
watch(() => form.orden.tipo_equipo_id, (id) => {
  const tipo = tipos.value.find((t) => t.id === id)
  form.orden.checklist = checklistPara(tipo?.checklist, form.orden.checklist)
})

// ── Cliente ──
const cliente = ref(null)
watch(cliente, (valor) => { form.orden.cliente_id = valor?.id ?? null })

const clienteDialog = ref(false)
const clienteFormRef = ref()

function clienteCreado (nuevo) {
  clienteDialog.value = false
  if (nuevo) cliente.value = nuevo
}

// ── Repuestos y totales (vista previa: el total lo calcula el backend) ──
function errorDe (i, campo) {
  return form.errors[`${PATH}.repuestos.${i}.${campo}`]
}

function agregar (variante) {
  const { variante_id: varianteId, cantidad, precio_unitario: precio } = nuevoItem(variante, cliente.value)
  form.orden.repuestos.push({ variante_id: varianteId, variante, cantidad, precio_unitario: precio })
}

function numero (valor) {
  const n = Number(valor)
  return valor !== '' && Number.isFinite(n) ? n : 0
}

function subtotalDe (item) {
  return Math.round(numero(item.cantidad) * numero(item.precio_unitario) * 100) / 100
}

const totalRepuestos = computed(() => form.orden.repuestos.reduce((suma, item) => suma + subtotalDe(item), 0))
const total = computed(() => totalRepuestos.value + numero(form.orden.mano_obra))

// Errores sin campo propio (caja cerrada para el adelanto, total por debajo
// de lo abonado…).
const errorGeneral = computed(() => form.errors.caja ?? form.errors['pedido.items'] ?? form.errors.estado ?? form.errors.sede ??
  form.errors[`${PATH}.cotizacion_id`] ?? form.errors.cotizacion_id ?? null)

// ── Carga ──
onMounted(async () => {
  const [tiposResp, marcasResp, tecnicosResp] = await Promise.all([
    TipoEquipoService.getData({ params: { rowsPerPage: 0, activo: 1, order_by: 'nombre' } }),
    MarcaService.getData({ params: { rowsPerPage: 0, activo: 1, order_by: 'nombre' } }),
    OrdenServicioService.tecnicos()
  ])
  tipos.value = tiposResp.data
  marcas.value = marcasResp.data
  marcasFiltradas.value = marcas.value
  tecnicos.value = tecnicosResp

  if (!props.id) {
    // Quien recibe suele ser quien la trabaja: se puede cambiar.
    form.orden.tecnico_id = tecnicos.value.some((t) => t.id === userStore.id) ? userStore.id : null
    if (props.cotizacionId) await desdeCotizacion(props.cotizacionId)
    return
  }

  const orden = await OrdenServicioService.get(props.id)
  cliente.value = orden.cliente
  // Un tipo o marca desactivados después siguen visibles en su orden.
  if (orden.tipo_equipo && !tipos.value.some((t) => t.id === orden.tipo_equipo_id)) tipos.value.push(orden.tipo_equipo)
  if (orden.marca && !marcas.value.some((m) => m.id === orden.marca_id)) marcas.value.push(orden.marca)

  form.setData({
    [PATH]: {
      ...formOrden().orden,
      cliente_id: orden.cliente_id,
      tecnico_id: orden.tecnico_id,
      tipo_equipo_id: orden.tipo_equipo_id,
      marca_id: orden.marca_id,
      modelo: orden.modelo ?? '',
      serie: orden.serie ?? '',
      accesorios: orden.accesorios ?? '',
      seguridad_tipo: orden.seguridad_tipo,
      seguridad_valor: orden.seguridad_valor ?? '',
      checklist: { ...checklistPara(orden.tipo_equipo?.checklist), ...orden.checklist },
      falla: orden.falla,
      diagnostico: orden.diagnostico ?? '',
      observacion: orden.observacion ?? '',
      es_garantia: orden.es_garantia,
      garantia_dias: orden.garantia_dias,
      mano_obra: Number(orden.mano_obra) ? orden.mano_obra : '',
      repuestos: (orden.repuestos ?? []).map((item) => ({
        variante_id: item.variante_id,
        variante: item.variante,
        cantidad: String(item.cantidad),
        precio_unitario: item.precio_unitario
      }))
    }
  })
})

// ── Desde una cotización ──
const cotizacion = ref(null)

/**
 * Lo cotizado pasa a la orden: el cliente, los productos del catálogo como
 * repuestos (al precio cotizado) y lo que no hay en tienda (servicios,
 * trabajos por encargo) como mano de obra. El equipo lo completa quien lo
 * recibe.
 */
async function desdeCotizacion (id) {
  const datos = await CotizacionService.get(id)
  cotizacion.value = datos
  if (datos.cliente) cliente.value = datos.cliente

  const libres = datos.items.filter((item) => !item.variante)
  const manoObra = libres.reduce((suma, item) => suma + Number(item.subtotal), 0)

  form.orden.cotizacion_id = datos.id
  form.orden.repuestos = datos.items.filter((item) => item.variante).map((item) => ({
    variante_id: item.variante_id,
    variante: item.variante,
    cantidad: String(item.cantidad),
    precio_unitario: item.precio_unitario
  }))
  form.orden.mano_obra = manoObra ? manoObra.toFixed(2) : ''
  form.orden.diagnostico = libres.map((item) => item.descripcion).join('\n')
  form.orden.observacion = `Cotización ${datos.codigo}`
  // "6 meses" → 180 días, si es una de las garantías de la orden.
  const garantia = GARANTIAS.find((g) => g.label === datos.garantia || (datos.garantia === '7 días' && g.value === 7))
  if (garantia) form.orden.garantia_dias = garantia.value
}

// El adelanto sin monto (o al editar) no se valida ni se cobra: el backend
// sólo lo toma al crear y con monto.
async function submit () {
  try {
    const respuesta = await form.submit()
    emit('save', respuesta?.data)
  } catch {
    // 422 / 409: los errores quedan en form.errors y se ven en cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.orden-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.orden-form__seccion {
  display: flex;
  flex-direction: column;
  gap: 12px;

  & + & {
    padding-top: 16px;
    border-top: 1px solid var(--app-border-subtle);
  }
}

.orden-form__titulo {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.2px;
  text-transform: uppercase;
  color: var(--app-ink-2);
}

.orden-form__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 12px 16px;
}

.orden-form__row--centro {
  align-items: center;
}

.orden-form__grow {
  flex: 1 1 260px;
  min-width: 0;
}

.orden-form__medio {
  flex: 1 1 180px;
  min-width: 0;
}

.orden-form__field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.orden-form__labelRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.orden-form__label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink);
}

.orden-form__requerido {
  color: var(--q-negative);
}

.orden-form__link {
  padding: 0;
  border: 0;
  background: none;
  font-size: 12px;
  font-weight: 600;
  color: var(--app-primary-hover);
  cursor: pointer;

  &:focus-visible {
    outline: 2px solid var(--q-primary);
    outline-offset: 2px;
  }
}

// Mismo radio, borde y foco que AppTextField.
.orden-form__control {
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

.orden-form__seguridad {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 14px;
  font-size: 13px;
}

.orden-form__checklist {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 10px 16px;
  padding: 12px 14px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
  font-size: 13px;
}

.orden-form__tablaWrap {
  overflow-x: auto;
}

.orden-form__tabla {
  width: 100%;
  min-width: 560px;
  border-collapse: collapse;

  th {
    padding: 0 6px 6px;
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    color: var(--app-ink-2);
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

.orden-form__producto {
  font-weight: 600;
}

.orden-form__detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.orden-form__numero {
  width: 130px;
}

.orden-form__subtotal {
  padding-top: 14px !important;
  font-weight: 600;
}

.orden-form__pie {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 24px;
}

.orden-form__anticipo {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.orden-form__totales {
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

.orden-form__total {
  padding-top: 8px;
  border-top: 1px solid var(--app-border-subtle);
  font-size: 16px;
  font-weight: 700;
  color: var(--app-ink) !important;
}

.orden-form__nota {
  margin: 0;
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.orden-form__aviso {
  margin: 4px 0 0;
  font-size: 11.5px;
  color: var(--q-warning);
}

.orden-form__desde {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--app-brand-soft);
  font-size: 13px;
  color: var(--app-ink);

  .q-icon {
    flex-shrink: 0;
    color: var(--app-brand-soft-ink);
  }
}

.orden-form__error {
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
