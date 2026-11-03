<template>
  <div class="app-list-page">
    <!-- ── Cabecera: volver, saltar a otro producto ── -->
    <div class="ficha__barra">
      <router-link
        to="/productos"
        class="ficha__volver"
      >
        <q-icon
          name="arrow_back"
          size="18px"
        />
        Productos
      </router-link>

      <q-select rounded
        v-model="saltarA"
        :options="opcionesSalto"
        use-input
        hide-dropdown-icon
        input-debounce="300"
        dense
        outlined
        emit-value
        map-options
        placeholder="Ir a otro producto: nombre, SKU o código…"
        class="ficha__salto"
        @filter="buscarProductos"
        @update:model-value="irA"
      >
        <template #option="scope">
          <q-item
            v-bind="scope.itemProps"
            class="ficha__opcion"
          >
            <q-item-section avatar>
              <img
                v-if="scope.opt.producto.portada"
                :src="scope.opt.producto.portada.miniatura_url"
                alt=""
                class="ficha__opcionFoto"
                loading="lazy"
              >
              <span
                v-else
                class="ficha__opcionFoto ficha__opcionFoto--vacia"
              >
                <q-icon
                  name="image"
                  size="16px"
                />
              </span>
            </q-item-section>
            <q-item-section>
              <q-item-label class="ficha__opcionNombre">
                {{ scope.opt.producto.nombre }}
              </q-item-label>
              <q-item-label caption>
                {{ [scope.opt.producto.marca?.nombre, scope.opt.producto.categoria?.nombre].filter(Boolean).join(' · ') || 'Sin marca ni categoría' }}
              </q-item-label>
              <q-item-label caption>
                {{ scope.opt.producto.variantes_count }} {{ scope.opt.producto.variantes_count === 1 ? 'presentación' : 'presentaciones' }}
                <template v-if="!scope.opt.producto.activo"> · <span class="text-negative">Inactivo</span></template>
              </q-item-label>
            </q-item-section>
            <q-item-section
              side
              class="ficha__opcionLado"
            >
              <span class="text-mono ficha__opcionPrecio">{{ formatearPrecio(scope.opt.producto.precio) }}</span>
              <span :class="['ficha__opcionStock', { 'ficha__opcionStock--cero': !Number(scope.opt.producto.stock_total) }]">
                Stock {{ formatearCantidad(scope.opt.producto.stock_total ?? 0) }}
              </span>
            </q-item-section>
          </q-item>
        </template>
        <template #prepend>
          <q-icon
            name="search"
            size="18px"
          />
        </template>
        <template #no-option>
          <q-item>
            <q-item-section class="text-grey">
              Ningún producto coincide.
            </q-item-section>
          </q-item>
        </template>
      </q-select>
    </div>

    <div
      v-if="cargando && !producto"
      class="ficha__cargando"
    >
      <q-spinner size="28px" />
    </div>

    <template v-else-if="producto">
      <header class="ficha__cabecera">
        <img
          v-if="portada"
          :src="portada"
          alt=""
          class="ficha__foto"
        >
        <span
          v-else
          class="ficha__foto ficha__foto--vacia"
        >
          <q-icon
            name="image"
            size="26px"
          />
        </span>

        <div class="ficha__titulo">
          <h1>{{ producto.nombre }}</h1>
          <p>
            {{ producto.marca?.nombre ?? 'Sin marca' }} · {{ producto.categoria?.nombre ?? 'Sin categoría' }}
          </p>
          <div class="ficha__chips">
            <AppChip
              :status="producto.activo ? 'positive' : 'negative'"
              :label="producto.activo ? 'Activo' : 'Inactivo'"
            />
            <AppChip
              v-if="producto.maneja_lotes"
              status="info"
              label="Maneja lotes"
            />
          </div>
        </div>

        <div class="ficha__acciones">
          <AppButton
            v-for="accion in acciones"
            :key="accion.tipo"
            :variant="accion.tipo === 'entrada' ? 'primary' : 'secondary'"
            :label="accion.label"
            :icon="accion.icon"
            :disable="!variantesSede.length"
            @click="abrirMovimiento(accion.tipo)"
          >
            <q-tooltip v-if="!variantesSede.length">
              {{ producto.activo ? 'Ninguna presentación se vende en tu sede' : 'El producto está inactivo' }}
            </q-tooltip>
          </AppButton>
          <AppButton
            v-if="userStore.hasPermission('productos.update')"
            variant="tertiary"
            label="Editar"
            icon="edit"
            @click="editarDialog = true"
          />
        </div>
      </header>

      <!-- ── Resumen ── -->
      <div class="ficha__stats">
        <AppStatTile
          :label="`Stock en ${userStore.sede?.nombre ?? 'tu sede'}`"
          :value="formatearCantidad(stockSede)"
          featured
        />
        <AppStatTile
          label="Stock en la empresa"
          :value="formatearCantidad(stockEmpresa)"
        />
        <AppStatTile
          label="Precio base"
          :value="formatearPrecio(producto.precio)"
        />
        <AppStatTile
          label="Presentaciones"
          :value="String(producto.variantes.length)"
        />
      </div>

      <!-- ── Lo que se olvidó cargar: costo, lote ── -->
      <div
        v-if="pendientes.length"
        class="ficha__pendientes"
      >
        <q-icon
          name="error_outline"
          size="20px"
        />
        <div>
          <strong>Faltan datos en este producto</strong>
          <ul>
            <li
              v-for="p in pendientes"
              :key="p"
            >
              {{ p }}
            </li>
          </ul>
        </div>
      </div>

      <!-- ── Presentaciones ── -->
      <AppCard class="ficha__bloque">
        <h2 class="ficha__subtitulo">
          Presentaciones
        </h2>
        <div class="ficha__tablaScroll">
          <table class="ficha__tabla">
            <thead>
              <tr>
                <th>Presentación</th>
                <th>SKU</th>
                <th class="text-right">
                  Precio
                </th>
                <th class="text-right">
                  Por mayor
                </th>
                <template v-if="veCostos">
                  <th class="text-right">
                    Costo
                    <q-icon
                      name="help_outline"
                      size="14px"
                    >
                      <q-tooltip>Costo promedio de lo que entró (compras, entradas, alta del producto).</q-tooltip>
                    </q-icon>
                  </th>
                  <th class="text-right">
                    Ganancia c/u
                  </th>
                </template>
                <th class="text-right">
                  En tu sede
                </th>
                <th class="text-right">
                  Mínimo
                </th>
                <th class="text-right">
                  Empresa
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="v in presentaciones"
                :key="v.id"
              >
                <td>
                  <div class="ficha__presentacion">
                    <span
                      v-if="v.color"
                      class="ficha__swatch"
                      :style="{ background: v.color.hexadecimal }"
                    />
                    <span>
                      {{ v.presentacion }}<template v-if="v.color"> · {{ v.color.nombre }}</template>
                    </span>
                    <AppChip
                      v-if="!v.sede?.activo"
                      status="warning"
                      label="No se vende acá"
                    />
                    <AppChip
                      v-else-if="v.bajoMinimo"
                      status="negative"
                      label="Bajo mínimo"
                    />
                  </div>
                </td>
                <td class="text-mono">
                  {{ v.sku }}
                </td>
                <td class="text-right text-mono">
                  {{ formatearPrecio(v.precioSede) }}
                </td>
                <td class="text-right text-mono">
                  {{ v.precio_mayor !== null && v.precio_mayor !== undefined ? formatearPrecio(v.precio_mayor) : '—' }}
                </td>
                <template v-if="veCostos">
                  <td class="text-right text-mono">
                    {{ v.costo_promedio !== null ? formatearPrecio(v.costo_promedio) : '—' }}
                  </td>
                  <td
                    v-if="v.ganancia !== null"
                    :class="['text-right', 'text-mono', v.ganancia > 0 ? 'ficha__mas' : 'ficha__menos']"
                  >
                    {{ formatearPrecio(v.ganancia) }}
                    <span class="ficha__margen">{{ v.margen }}%</span>
                  </td>
                  <td
                    v-else
                    class="text-right ficha__detalle"
                  >
                    Sin costo
                  </td>
                </template>
                <td class="text-right text-mono ficha__fuerte">
                  {{ formatearCantidad(v.sede?.cantidad ?? 0) }}
                </td>
                <td class="text-right text-mono">
                  {{ formatearCantidad(v.sede?.stock_minimo ?? 0) }}
                </td>
                <td class="text-right text-mono">
                  {{ formatearCantidad(v.stock) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>

      <!-- ── Lotes en la sede ── -->
      <AppCard
        v-if="userStore.hasPermission('inventario.lotes')"
        class="ficha__bloque"
      >
        <h2 class="ficha__subtitulo">
          Lotes en tu sede
        </h2>

        <!-- Sin lotes: cómo activarlos. -->
        <div
          v-if="!producto.maneja_lotes"
          class="ficha__vacio"
        >
          <p>
            Este producto no controla lotes ni vencimiento. Si vence (cemento, aditivos, pegamentos…),
            activalo en <strong>Editar → “Maneja lotes y vencimiento”</strong>.
            <template v-if="stockSede > 0">
              El stock que ya tenés va a quedar “sin lote” y acá vas a poder asignarle su lote.
            </template>
          </p>
          <AppButton
            v-if="userStore.hasPermission('productos.update')"
            variant="secondary"
            label="Activar lotes"
            icon="edit"
            @click="editarDialog = true"
          />
        </div>

        <p
          v-else-if="!lotes.length && !sinLote.length"
          class="ficha__vacio"
        >
          No hay lotes con stock. Se crean al registrar una <strong>Entrada</strong> (botón de arriba):
          cada una pide el lote y el vencimiento del envase.
        </p>

        <div
          v-else
          class="ficha__tablaScroll"
        >
          <table class="ficha__tabla">
            <thead>
              <tr>
                <th>Lote</th>
                <th>Presentación</th>
                <th>Vence</th>
                <th class="text-right">
                  Cantidad
                </th>
              </tr>
            </thead>
            <tbody>
              <!-- Stock que entró antes de activar lotes (p. ej. el inicial). -->
              <tr
                v-for="v in sinLote"
                :key="`sin-${v.id}`"
                class="ficha__sinLote"
              >
                <td>
                  <AppChip
                    status="warning"
                    label="Sin lote"
                  />
                </td>
                <td>{{ v.presentacion }}</td>
                <td>
                  <AppButton
                    v-if="puedeCorregir"
                    variant="tertiary"
                    label="Asignar lote"
                    icon="qr_code_2"
                    @click="abrirAsignar(v)"
                  />
                  <span v-else>—</span>
                </td>
                <td class="text-right text-mono">
                  {{ formatearCantidad(v.cantidadSinLote) }}
                </td>
              </tr>
              <tr
                v-for="lote in lotes"
                :key="lote.id"
              >
                <td class="text-mono">
                  {{ lote.codigo }}
                </td>
                <td>{{ lote.variante?.presentacion }}</td>
                <td>
                  <span v-if="lote.vence_at">{{ fechaCorta(lote.vence_at) }}</span>
                  <span v-else>—</span>
                  <AppChip
                    v-if="lote.estado === 'vencido' || lote.estado === 'por_vencer'"
                    :status="lote.estado === 'vencido' ? 'negative' : 'warning'"
                    :label="lote.estado === 'vencido' ? 'Vencido' : 'Por vencer'"
                    class="q-ml-sm"
                  />
                </td>
                <td class="text-right text-mono">
                  {{ formatearCantidad(lote.cantidad) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>

      <!-- ── Movimientos ── -->
      <template v-if="userStore.hasPermission('inventario.index')">
        <div class="ficha__movTitulo">
          <h2 class="ficha__subtitulo">
            Movimientos de inventario
          </h2>
          <AppFilterPill
            v-model="tipoFilter"
            label="Tipo"
            :options="tipoOptions"
          />
          <AppFilterPill
            v-if="sedes.length > 1"
            v-model="sedeFilter"
            label="Sede"
            :options="sedeOptions"
          />
        </div>

        <AppTable
          ref="tableRef"
          v-model:pagination="pagination"
          :rows="movimientos"
          :columns="columns"
          :loading="cargandoMovimientos"
          :filter="filtroTabla"
          no-data-label="Este producto todavía no tiene movimientos."
          @request="onRequest"
        >
          <template #body-cell-fecha="props">
            <q-td
              :props="props"
              class="text-mono ficha__fecha"
            >
              {{ formatearFecha(props.row.fecha) }}
            </q-td>
          </template>

          <template #body-cell-tipo="props">
            <q-td :props="props">
              <AppChip
                :status="TIPOS[props.row.tipo].status"
                :label="TIPOS[props.row.tipo].label"
              />
            </q-td>
          </template>

          <template #body-cell-variante="props">
            <q-td :props="props">
              <div>{{ props.row.variante.presentacion }}</div>
              <div class="ficha__detalle text-mono">
                {{ props.row.variante.sku }}
              </div>
            </q-td>
          </template>

          <template #body-cell-cantidad="props">
            <q-td
              :props="props"
              :class="['text-right', 'text-mono', props.row.cantidad > 0 ? 'ficha__mas' : 'ficha__menos']"
            >
              {{ props.row.cantidad > 0 ? '+' : '' }}{{ formatearCantidad(props.row.cantidad) }}
            </q-td>
          </template>

          <template #body-cell-detalle="props">
            <q-td :props="props">
              <div v-if="props.row.motivo_label">
                {{ props.row.motivo_label }}
                <template v-if="props.row.sede_relacionada">
                  {{ props.row.cantidad < 0 ? 'a' : 'desde' }} {{ props.row.sede_relacionada.nombre }}
                </template>
              </div>
              <div
                v-if="props.row.referencia"
                class="ficha__detalle"
              >
                Ref. {{ props.row.referencia }}
              </div>
              <div
                v-for="lote in props.row.lotes ?? []"
                :key="lote.codigo"
                class="ficha__detalle"
              >
                Lote {{ lote.codigo }}<template v-if="lote.vence_at">
                  · vence {{ fechaCorta(lote.vence_at) }}
                </template>
              </div>
              <div
                v-if="props.row.costo_unitario !== null"
                class="ficha__detalle"
              >
                {{ formatearPrecio(props.row.costo_unitario) }} c/u
              </div>
              <div
                v-if="faltantes(props.row).length || corregible(props.row)"
                class="ficha__faltantes"
              >
                <AppChip
                  v-for="f in faltantes(props.row)"
                  :key="f"
                  status="warning"
                  :label="f"
                />
                <q-btn
                  v-if="corregible(props.row)"
                  flat
                  dense
                  round
                  size="sm"
                  icon="edit"
                  color="grey-7"
                  :aria-label="faltantes(props.row).length ? 'Completar datos' : 'Corregir datos'"
                  @click="abrirCorregir(props.row)"
                >
                  <q-tooltip>{{ faltantes(props.row).length ? 'Completar datos' : 'Corregir costo, factura o lote' }}</q-tooltip>
                </q-btn>
              </div>
            </q-td>
          </template>
        </AppTable>
      </template>
    </template>

    <!-- ── Registrar movimiento con las presentaciones ya cargadas ── -->
    <AppDialog
      v-model="movimientoDialog"
      :title="tituloMovimiento"
      size="lg"
      persistent
    >
      <MovimientoForm
        v-if="tipoAbierto"
        ref="movimientoRef"
        :key="`${tipoAbierto}-${aperturas}`"
        :tipo="tipoAbierto"
        :iniciales="variantesSede"
        @save="movimientoGuardado"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="movimientoDialog = false"
        />
        <AppButton
          variant="primary"
          label="Registrar"
          :loading="movimientoRef?.form.processing"
          @click="movimientoRef.submit()"
        />
      </template>
    </AppDialog>

    <AppDialog
      v-model="corregirDialog"
      title="Completar datos de la entrada"
      persistent
    >
      <CorregirEntradaForm
        v-if="corregirDialog && aCorregir"
        ref="corregirRef"
        :movimiento="aCorregir"
        :maneja-lotes="producto?.maneja_lotes ?? false"
        @save="corregida"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="corregirDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar"
          :loading="corregirRef?.procesando"
          @click="corregirRef.submit()"
        />
      </template>
    </AppDialog>

    <AppDialog
      v-model="asignarDialog"
      title="Asignar lote"
      persistent
    >
      <AsignarLoteForm
        v-if="asignarDialog && aAsignar"
        ref="asignarRef"
        :presentacion="aAsignar"
        :sin-lote="aAsignar.cantidadSinLote"
        :sede="userStore.sede?.nombre ?? 'tu sede'"
        @save="loteAsignado"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="asignarDialog = false"
        />
        <AppButton
          variant="primary"
          label="Asignar"
          :loading="asignarRef?.procesando"
          @click="asignarRef.submit()"
        />
      </template>
    </AppDialog>

    <AppDialog
      v-model="editarDialog"
      :title="`Editar ${producto?.nombre ?? ''}`"
      size="lg"
      persistent
    >
      <ProductosForm
        v-if="editarDialog && producto"
        :id="producto.id"
        ref="editarRef"
        @save="editado"
      />
      <template #actions>
        <AppButton
          variant="tertiary"
          label="Cancelar"
          @click="editarDialog = false"
        />
        <AppButton
          variant="primary"
          label="Guardar"
          :loading="editarRef?.form.processing"
          @click="editarRef.submit()"
        />
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppChip from '@/components/AppChip.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppFilterPill from '@/components/AppFilterPill.vue'
import AppStatTile from '@/components/AppStatTile.vue'
import AppTable from '@/components/AppTable.vue'
import AsignarLoteForm from '@/modules/Inventario/AsignarLoteForm.vue'
import CorregirEntradaForm from '@/modules/Inventario/CorregirEntradaForm.vue'
import MovimientoForm from '@/modules/Inventario/MovimientoForm.vue'
import { TIPOS } from '@/modules/Inventario/constantes'
import InventarioService from '@/services/InventarioService'
import ProductoService from '@/services/ProductoService'
import SedeService from '@/services/SedeService'
import { useUserStore } from '@/stores/user-store'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'
import ProductosForm from './ProductosForm.vue'

/**
 * Ficha de un producto: cómo está en la sede (stock por presentación, lotes),
 * su historial de inventario y las acciones de inventario con sus
 * presentaciones ya cargadas. `?entrada=1` abre la entrada al llegar (recién
 * creado: el stock inicial —y su lote— se carga con la primera entrada).
 */
const props = defineProps({
  id: {
    type: Number,
    required: true
  }
})

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const producto = ref(null)
const cargando = ref(true)
// Presentaciones vendibles en la sede, con su stock ahí: las líneas de los
// movimientos (misma forma que el buscador de inventario).
const variantesSede = ref([])
const lotes = ref([])

async function cargar () {
  cargando.value = true
  try {
    const [p, v] = await Promise.all([
      ProductoService.get(props.id),
      acciones.value.length || userStore.hasPermission('inventario.index')
        ? InventarioService.variantes({ params: { producto_id: props.id, rowsPerPage: 0 } })
        : Promise.resolve({ data: [] })
    ])
    producto.value = p
    variantesSede.value = v.data
    await cargarLotes()
  } finally {
    cargando.value = false
  }
}

async function cargarLotes () {
  if (!producto.value?.maneja_lotes || !userStore.hasPermission('inventario.lotes')) {
    lotes.value = []
    return
  }
  lotes.value = (await InventarioService.lotes({ params: { producto_id: props.id, rowsPerPage: 0 } })).data
}

const portada = computed(() => producto.value?.archivos?.[0]?.miniatura_url ?? null)

// Cada presentación con lo de la sede del usuario (stock, mínimo, precio).
const presentaciones = computed(() => (producto.value?.variantes ?? []).map((v) => {
  const sede = v.stocks?.find((s) => s.sede_id === userStore.sedeId) ?? null
  const cantidad = Number(sede?.cantidad ?? 0)
  const minimo = Number(sede?.stock_minimo ?? 0)
  return {
    ...v,
    sede,
    precioSede: sede?.precio ?? v.precio ?? producto.value.precio,
    ...ganancia(sede?.precio ?? v.precio ?? producto.value.precio, v.costo_promedio),
    bajoMinimo: minimo > 0 && cantidad < minimo
  }
}))

const stockSede = computed(() => presentaciones.value.reduce((s, v) => s + Number(v.sede?.cantidad ?? 0), 0))
// El backend manda el costo sólo a quien puede verlo.
const veCostos = computed(() => (producto.value?.variantes ?? []).some((v) => 'costo_promedio' in v))

// Ganancia por unidad y margen sobre el precio (ambos con IGV incluido).
// null = todavía no hay costo (nunca entró mercadería con costo).
function ganancia (precio, costo) {
  if (costo === null || costo === undefined) return { ganancia: null, margen: null }
  const p = Number(precio)
  const g = Math.round((p - Number(costo)) * 100) / 100
  return { ganancia: g, margen: p > 0 ? Math.round((g / p) * 1000) / 10 : 0 }
}

const stockEmpresa = computed(() => presentaciones.value.reduce((s, v) => s + Number(v.stock ?? 0), 0))

// ── Datos que faltan ──
// Stock de la sede que no está en ningún lote: lo que entró antes de
// activar lotes en el producto (p. ej. el stock inicial).
const sinLote = computed(() => {
  if (!producto.value?.maneja_lotes) return []
  return presentaciones.value
    .map((v) => {
      const enLotes = lotes.value
        .filter((l) => l.variante?.id === v.id)
        .reduce((s, l) => s + Number(l.cantidad), 0)
      return { ...v, cantidadSinLote: Math.round((Number(v.sede?.cantidad ?? 0) - enLotes) * 1000) / 1000 }
    })
    .filter((v) => v.cantidadSinLote > 0)
})

const pendientes = computed(() => {
  const lista = []
  const sinCosto = veCostos.value ? presentaciones.value.filter((v) => v.costo_promedio === null && Number(v.stock) > 0) : []
  if (sinCosto.length) {
    lista.push(`${sinCosto.length === 1 ? '1 presentación' : `${sinCosto.length} presentaciones`} con stock y sin costo de compra (${sinCosto.map((v) => v.presentacion).join(', ')}): completalo con el lápiz en su entrada, abajo en Movimientos.`)
  }
  if (sinLote.value.length) {
    lista.push(`Stock sin lote en tu sede: ${sinLote.value.map((v) => `${formatearCantidad(v.cantidadSinLote)} de ${v.presentacion}`).join(', ')}. Asignáselo en “Lotes en tu sede”.`)
  }
  return lista
})

// ── Completar / corregir una entrada ──
// Quien registra entradas puede completarlas (mismo permiso en el backend).
const puedeCorregir = computed(() => userStore.hasPermission('inventario.entradas') || userStore.hasPermission('inventario.corregir'))

// Entradas manuales o de alta de producto; las de una compra se corrigen
// desde la compra.
function corregible (row) {
  return puedeCorregir.value &&
    row.tipo === 'entrada' &&
    !row.compra_id &&
    (row.motivo === null || row.motivo === 'alta_producto')
}

function faltantes (row) {
  if (row.tipo !== 'entrada' || row.compra_id || !(row.motivo === null || row.motivo === 'alta_producto')) return []
  const lista = []
  if (veCostos.value && row.costo_unitario === null) lista.push('Sin costo')
  if (!row.referencia) lista.push('Sin factura')
  if (producto.value?.maneja_lotes) {
    if (!row.lotes?.length) lista.push('Sin lote')
    else if (row.lotes.some((l) => !l.vence_at)) lista.push('Sin vencimiento')
  }
  return lista
}

const corregirDialog = ref(false)
const corregirRef = ref()
const aCorregir = ref(null)

function abrirCorregir (row) {
  aCorregir.value = row
  corregirDialog.value = true
}

async function corregida () {
  corregirDialog.value = false
  $q.notify({ type: 'positive', message: 'Entrada actualizada.', position: 'top-right', timeout: 2000 })
  await cargar()
  recargarMovimientos()
}

// ── Asignar lote al stock sin lote ──
const asignarDialog = ref(false)
const asignarRef = ref()
const aAsignar = ref(null)

function abrirAsignar (v) {
  aAsignar.value = v
  asignarDialog.value = true
}

async function loteAsignado (lote) {
  asignarDialog.value = false
  $q.notify({ type: 'positive', message: `Lote ${lote.codigo} asignado.`, position: 'top-right', timeout: 2000 })
  await cargarLotes()
}

// ── Saltar a otro producto ──
const saltarA = ref(null)
const opcionesSalto = ref([])

async function buscarProductos (texto, update) {
  // Con la sede del usuario: el stock que se muestra es el de acá.
  const { data } = await ProductoService.getData({
    params: { search: texto.trim(), rowsPerPage: 15, order_by: 'nombre', sede_id: userStore.sedeId ?? 0 }
  })
  update(() => {
    opcionesSalto.value = data
      .filter((p) => p.id !== props.id)
      .map((p) => ({ label: p.nombre, value: p.id, producto: p }))
  })
}

function irA (id) {
  if (id) router.push(`/productos/${id}`)
  saltarA.value = null
}

// ── Movimientos (paginación en el servidor) ──
const columns = [
  { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left', sortable: true },
  { name: 'tipo', label: 'Tipo', field: 'tipo', align: 'left' },
  { name: 'sede', label: 'Sede', field: (row) => row.sede?.nombre ?? '—', align: 'left' },
  { name: 'variante', label: 'Presentación', field: (row) => row.variante?.sku, align: 'left' },
  { name: 'cantidad', label: 'Cantidad', field: 'cantidad', align: 'right' },
  { name: 'stock_resultante', label: 'Stock en sede', field: 'stock_resultante', align: 'right', classes: 'text-mono', format: (v) => formatearCantidad(v) },
  { name: 'detalle', label: 'Detalle', field: 'motivo', align: 'left' },
  { name: 'usuario', label: 'Usuario', field: (row) => row.usuario?.name ?? '—', align: 'left' }
]

const tableRef = ref()
const movimientos = ref([])
const cargandoMovimientos = ref(false)
const pagination = ref({ sortBy: 'fecha', descending: true, page: 1, rowsPerPage: 10, rowsNumber: 0 })

const tipoFilter = ref(null)
const tipoOptions = [
  { label: 'Todos', value: null },
  ...Object.entries(TIPOS).filter(([, t]) => !t.soloAccion).map(([value, { label }]) => ({ label, value }))
]
const sedeFilter = ref(userStore.sedeId)
const sedes = ref([])
const sedeOptions = computed(() => [
  { label: 'Todas', value: null },
  ...sedes.value.map((s) => ({ label: s.nombre, value: s.id }))
])

// El id va en el filtro: al saltar a otro producto, la tabla vuelve a la página 1.
const filtroTabla = computed(() => JSON.stringify({ id: props.id, tipo: tipoFilter.value, sede: sedeFilter.value }))

async function onRequest ({ pagination: requested }) {
  const { page, rowsPerPage, sortBy, descending } = requested
  cargandoMovimientos.value = true
  try {
    const columna = sortBy === 'fecha' ? 'id' : sortBy
    const params = { producto_id: props.id, rowsPerPage, page, order_by: descending ? `-${columna}` : columna }
    if (tipoFilter.value) params.tipo = tipoFilter.value
    if (sedeFilter.value) params.sede_id = sedeFilter.value

    const { data, total = 0 } = await InventarioService.getData({ params })
    movimientos.value = data
    pagination.value = { ...requested, rowsNumber: total }
  } finally {
    cargandoMovimientos.value = false
  }
}

function recargarMovimientos () {
  tableRef.value?.requestServerInteraction()
}

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })
function formatearFecha (iso) {
  return iso ? formatoFecha.format(new Date(iso)) : ''
}

function fechaCorta (iso) {
  return iso.split('-').reverse().join('/')
}

// ── Acciones de inventario ──
const acciones = computed(() => Object.entries(TIPOS)
  .filter(([, tipo]) => userStore.hasPermission(tipo.permiso))
  .map(([clave, tipo]) => ({ tipo: clave, ...tipo })))

const movimientoDialog = ref(false)
const movimientoRef = ref()
const tipoAbierto = ref(null)
const aperturas = ref(0)

const tituloMovimiento = computed(() => {
  if (!tipoAbierto.value) return ''
  return `${TIPOS[tipoAbierto.value].titulo} · ${producto.value?.nombre ?? ''}`
})

function abrirMovimiento (tipo) {
  tipoAbierto.value = tipo
  aperturas.value++
  movimientoDialog.value = true
}

async function movimientoGuardado (registrados) {
  movimientoDialog.value = false
  const n = registrados.length
  $q.notify({
    type: 'positive',
    message: n === 0
      ? 'El conteo coincide con el sistema: no hubo diferencias que registrar.'
      : `${n} ${n === 1 ? 'movimiento registrado' : 'movimientos registrados'}.`,
    position: 'top-right',
    timeout: 2500
  })
  await cargar()
  recargarMovimientos()
}

// ── Editar ──
const editarDialog = ref(false)
const editarRef = ref()

async function editado () {
  editarDialog.value = false
  $q.notify({ type: 'positive', message: 'Producto guardado.', position: 'top-right', timeout: 1500 })
  await cargar()
}

// ── Carga ──
// Recién creado (?entrada=1): se abre la entrada para cargar el stock
// inicial; el query se limpia para que recargar no la vuelva a abrir.
async function iniciar () {
  await cargar()
  if (route.query.entrada && variantesSede.value.length && userStore.hasPermission('inventario.entradas')) {
    router.replace({ query: {} })
    abrirMovimiento('entrada')
  }
}

// Al saltar a otro producto los movimientos se recargan solos: el id está en
// `filtroTabla` y AppTable pide datos cuando cambia.
watch(() => props.id, iniciar)

onMounted(async () => {
  iniciar().then(async () => {
    await nextTick()
    recargarMovimientos()
  })
  if (userStore.hasPermission('sedes.index')) sedes.value = await SedeService.activas()
})
</script>

<style lang="scss" scoped>
.ficha__pendientes {
  display: flex;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 10px;
  // El suave de marca: definido para los dos temas.
  background: var(--app-brand-soft);
  color: var(--app-ink);
  font-size: 13.5px;

  ul {
    margin: 4px 0 0;
    padding-left: 18px;
  }
}

.ficha__faltantes {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}

.ficha__barra {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.ficha__volver {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  color: var(--app-ink-2);
  text-decoration: none;

  &:hover {
    color: $primary;
  }
}

.ficha__salto {
  width: 380px;
  max-width: 100%;
}

// El menú se teletransporta a <body>: el scope llega a lo que está escrito en
// este template (las clases de adentro), no a la clase del popup.
.ficha__opcion {
  min-width: 360px;
  padding: 8px 12px;
}

.ficha__opcionFoto {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  object-fit: cover;

  &--vacia {
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--app-border-subtle);
    color: var(--app-ink-2);
  }
}

.ficha__opcionNombre {
  font-weight: 600;
}

.ficha__opcionLado {
  align-items: flex-end;
  gap: 2px;
}

.ficha__opcionPrecio {
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink);
}

.ficha__opcionStock {
  font-size: 12px;
  color: var(--app-ink-2);

  &--cero {
    color: var(--q-negative);
  }
}

.ficha__cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}

.ficha__cabecera {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.ficha__foto {
  width: 72px;
  height: 72px;
  border-radius: 12px;
  object-fit: cover;
  flex-shrink: 0;

  &--vacia {
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--app-border-subtle);
    background: var(--app-surface);
    color: var(--app-ink-2);
  }
}

.ficha__titulo {
  flex: 1;
  min-width: 220px;

  h1 {
    margin: 0;
    font-size: 25px;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.5px;
  }

  p {
    margin: 4px 0 8px;
    font-size: 14px;
    color: var(--app-ink-2);
  }
}

.ficha__chips {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.ficha__acciones {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.ficha__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 14px;
}

.ficha__bloque {
  padding: 18px 20px;
}

.ficha__subtitulo {
  margin: 0 0 12px;
  font-size: 16px;
  font-weight: 700;
  line-height: 1.3;
  color: var(--app-ink);
}

.ficha__movTitulo {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;

  .ficha__subtitulo {
    flex: 1;
    margin: 0;
  }
}

.ficha__vacio {
  margin: 0;
  font-size: 13px;
  color: var(--app-ink-2);

  p {
    margin: 0 0 10px;
  }
}

.ficha__tablaScroll {
  overflow-x: auto;
}

.ficha__tabla {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;

  th {
    padding: 8px 10px;
    border-bottom: 1px solid var(--app-border-subtle);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-align: left;
    text-transform: uppercase;
    color: var(--app-ink-2);
    white-space: nowrap;
  }

  td {
    padding: 10px;
    border-bottom: 1px solid var(--app-border-subtle);
    color: var(--app-ink);
  }

  tr:last-child td {
    border-bottom: 0;
  }

  .text-right {
    text-align: right;
  }
}

.ficha__presentacion {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.ficha__swatch {
  width: 14px;
  height: 14px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 4px;
}

.ficha__margen {
  margin-left: 4px;
  font-size: 11.5px;
  font-weight: 500;
  opacity: 0.85;
}

.ficha__fuerte {
  font-weight: 700;
}

.ficha__fecha {
  white-space: nowrap;
  color: var(--app-ink-2);
}

.ficha__detalle {
  font-size: 12px;
  color: var(--app-ink-2);
}

.ficha__mas {
  font-weight: 600;
  color: var(--q-positive);
}

.ficha__menos {
  font-weight: 600;
  color: var(--q-negative);
}
</style>
