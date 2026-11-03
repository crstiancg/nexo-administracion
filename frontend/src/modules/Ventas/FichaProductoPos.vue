<template>
  <AppDialog
    v-model="abierto"
    :title="producto?.nombre ?? 'Producto'"
    size="lg"
  >
    <div
      v-if="cargando"
      class="ficha-pos__cargando"
    >
      <q-spinner size="28px" />
    </div>

    <div
      v-else-if="ficha"
      class="ficha-pos"
    >
      <!-- ── Fotos + datos generales ── -->
      <div class="ficha-pos__cabecera">
        <div class="ficha-pos__galeria">
          <img
            v-if="fotoActual"
            :src="fotoActual"
            alt=""
            class="ficha-pos__foto"
          >
          <span
            v-else
            class="ficha-pos__foto ficha-pos__foto--vacia"
          >
            <q-icon
              name="image"
              size="32px"
            />
          </span>
          <div
            v-if="ficha.fotos.length > 1"
            class="ficha-pos__miniaturas"
          >
            <button
              v-for="(foto, i) in ficha.fotos"
              :key="foto.url"
              type="button"
              :class="['ficha-pos__miniatura', { 'ficha-pos__miniatura--activa': i === fotoIndice }]"
              :aria-label="`Ver foto ${i + 1}`"
              @click="fotoIndice = i"
            >
              <img
                :src="foto.miniatura_url"
                alt=""
              >
            </button>
          </div>
        </div>

        <div class="ficha-pos__datos">
          <p class="ficha-pos__meta">
            {{ [ficha.marca?.nombre, ficha.categoria?.nombre].filter(Boolean).join(' · ') }}
          </p>
          <!-- HTML limpio en el backend (App\Support\HtmlSeguro: lista blanca). -->
          <!-- eslint-disable-next-line vue/no-v-html -->
          <div
            v-if="ficha.descripcion"
            class="ficha-pos__descripcion"
            v-html="ficha.descripcion"
          />
          <p
            v-else
            class="ficha-pos__descripcion ficha-pos__descripcion--vacia"
          >
            Sin descripción.
          </p>
          <div class="ficha-pos__resumen">
            <div>
              <span>Stock aquí</span>
              <strong class="text-mono">{{ formatearCantidad(stockAqui) }}</strong>
            </div>
            <div>
              <span>Presentaciones</span>
              <strong class="text-mono">{{ ficha.variantes.length }}</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Presentaciones ── -->
      <div class="ficha-pos__lista">
        <div
          v-for="v in ficha.variantes"
          :key="v.id"
          class="ficha-pos__variante"
        >
          <div class="ficha-pos__fila">
            <div class="ficha-pos__nombre">
              <span
                v-if="v.color"
                class="ficha-pos__swatch"
                :style="{ background: v.color.hexadecimal }"
              />
              <div>
                <div class="ficha-pos__presentacion">
                  {{ v.presentacion }}<template v-if="v.color">
                    · {{ v.color.nombre }}
                  </template>
                </div>
                <div class="ficha-pos__codigos text-mono">
                  {{ v.sku }}<template v-if="v.codigo_barras">
                    · {{ v.codigo_barras }}
                  </template>
                </div>
              </div>
            </div>

            <div class="ficha-pos__precio">
              <s
                v-if="Number(v.precio) < Number(v.precio_lista)"
                class="text-mono"
              >{{ formatearPrecio(v.precio_lista) }}</s>
              <strong class="text-mono">{{ formatearPrecio(v.precio) }}</strong>
              <span
                v-if="v.oferta"
                class="ficha-pos__oferta"
              >{{ v.oferta }}</span>
              <span
                v-if="v.precio_mayor !== null && v.precio_mayor !== undefined"
                class="ficha-pos__mayor text-mono"
              >Por mayor {{ formatearPrecio(v.precio_mayor) }}</span>
            </div>

            <div :class="['ficha-pos__stock', `ficha-pos__stock--${nivel(v.stock)}`]">
              {{ v.stock > 0 ? `${formatearCantidad(v.stock)} ${v.unidad?.abreviatura ?? ''}` : 'Agotado' }}
            </div>
          </div>

          <div
            v-if="v.otras_sedes.length || v.lotes.length"
            class="ficha-pos__extra"
          >
            <span v-if="v.otras_sedes.length">
              <q-icon
                name="storefront"
                size="14px"
              />
              También en: {{ v.otras_sedes.map((s) => `${s.sede} (${formatearCantidad(s.cantidad)})`).join(', ') }}
            </span>
            <span
              v-for="lote in v.lotes"
              :key="lote.codigo"
              :class="['ficha-pos__lote', `ficha-pos__lote--${lote.estado}`]"
            >
              Lote {{ lote.codigo }} · {{ formatearCantidad(lote.cantidad) }}
              <template v-if="lote.vence_at">
                · vence {{ fechaCorta(lote.vence_at) }}
              </template>
              <template v-if="lote.estado === 'vencido'">
                (vencido, no se vende)
              </template>
            </span>
          </div>
        </div>

        <p
          v-if="!ficha.variantes.length"
          class="ficha-pos__vacio"
        >
          Esta sede no vende ninguna presentación de este producto.
        </p>
      </div>
    </div>

    <template #actions>
      <AppButton
        variant="tertiary"
        label="Cerrar"
        @click="abierto = false"
      />
      <AppButton
        v-if="producto?.stock_total > 0"
        variant="primary"
        label="Agregar al carrito"
        icon="add_shopping_cart"
        @click="agregar"
      />
    </template>
  </AppDialog>
</template>

<script setup>
import { computed, ref } from 'vue'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import VentaService from '@/services/VentaService'
import { formatearCantidad } from '@/utils/cantidad'
import { formatearPrecio } from '@/utils/moneda'

/**
 * Ficha de sólo lectura del punto de venta: lo que el vendedor necesita para
 * responder al cliente (precio de hoy, stock aquí y en otras sedes,
 * vencimientos) sin salir del POS ni tener permisos de productos.
 */
const emit = defineEmits(['elegir'])

const abierto = ref(false)
const cargando = ref(false)
// El producto del catálogo (para "Agregar al carrito") y su ficha completa.
const producto = ref(null)
const ficha = ref(null)
const fotoIndice = ref(0)

async function abrir (productoCatalogo) {
  producto.value = productoCatalogo
  ficha.value = null
  fotoIndice.value = 0
  abierto.value = true
  cargando.value = true
  try {
    ficha.value = await VentaService.ficha(productoCatalogo.id)
  } finally {
    cargando.value = false
  }
}

const fotoActual = computed(() => ficha.value?.fotos[fotoIndice.value]?.url ?? producto.value?.miniatura_url ?? null)

const stockAqui = computed(() => (ficha.value?.variantes ?? []).reduce((s, v) => s + Number(v.stock), 0))

function nivel (stock) {
  if (!stock) return 'agotado'
  return stock <= 5 ? 'bajo' : 'ok'
}

function fechaCorta (iso) {
  return iso.split('-').reverse().join('/')
}

function agregar () {
  abierto.value = false
  emit('elegir', producto.value)
}

defineExpose({ abrir })
</script>

<style lang="scss" scoped>
.ficha-pos__cargando {
  display: flex;
  justify-content: center;
  padding: 40px;
}

.ficha-pos {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.ficha-pos__cabecera {
  display: grid;
  grid-template-columns: 200px minmax(0, 1fr);
  gap: 18px;
}

.ficha-pos__galeria {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ficha-pos__foto {
  width: 100%;
  aspect-ratio: 1 / 1;
  border-radius: 12px;
  object-fit: cover;

  &--vacia {
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--app-border-subtle);
    color: var(--app-ink-2);
  }
}

.ficha-pos__miniaturas {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.ficha-pos__miniatura {
  width: 40px;
  height: 40px;
  padding: 0;
  overflow: hidden;
  border: 2px solid transparent;
  border-radius: 8px;
  background: none;
  cursor: pointer;

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  &--activa {
    border-color: $primary;
  }
}

.ficha-pos__meta {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--app-ink-2);
}

.ficha-pos__descripcion {
  margin: 0 0 14px;

  :deep(p),
  :deep(ul),
  :deep(ol),
  :deep(h3),
  :deep(h4) {
    margin: 0 0 6px;
  }

  :deep(h3),
  :deep(h4) {
    font-size: 14px;
    font-weight: 700;
    line-height: 1.4;
  }

  :deep(ul),
  :deep(ol) {
    padding-left: 20px;
  }

  font-size: 14px;
  line-height: 1.55;
  color: var(--app-ink);
  white-space: pre-line;

  &--vacia {
    color: var(--app-ink-2);
  }
}

.ficha-pos__resumen {
  display: flex;
  gap: 24px;

  div {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  span {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--app-ink-2);
  }

  strong {
    font-size: 22px;
    color: var(--app-ink);
  }
}

.ficha-pos__lista {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--app-border-subtle);
  border-radius: 12px;
}

.ficha-pos__variante {
  padding: 12px 14px;

  & + & {
    border-top: 1px solid var(--app-border-subtle);
  }
}

.ficha-pos__fila {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto 110px;
  align-items: center;
  gap: 12px;
}

.ficha-pos__nombre {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.ficha-pos__swatch {
  flex-shrink: 0;
  width: 14px;
  height: 14px;
  border: 1px solid var(--app-border-control);
  border-radius: 50%;
}

.ficha-pos__presentacion {
  font-size: 14px;
  font-weight: 600;
  color: var(--app-ink);
}

.ficha-pos__codigos {
  font-size: 11.5px;
  color: var(--app-ink-2);
}

.ficha-pos__precio {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  align-items: baseline;
  gap: 6px;

  s {
    font-size: 12px;
    color: var(--app-ink-2);
  }

  strong {
    font-size: 15px;
    color: var(--app-ink);
  }
}

.ficha-pos__mayor {
  padding: 1px 6px;
  border-radius: 5px;
  background: var(--app-brand-soft);
  font-size: 11px;
  font-weight: 700;
  color: var(--app-brand-soft-ink);
  white-space: nowrap;
}

.ficha-pos__oferta {
  padding: 1px 6px;
  border-radius: 5px;
  background: #DC2626;
  font-size: 11px;
  font-weight: 700;
  color: #FFFFFF;
}

.ficha-pos__stock {
  font-size: 13px;
  font-weight: 700;
  text-align: right;

  &--ok {
    color: var(--q-positive);
  }

  &--bajo {
    color: var(--q-warning);
  }

  &--agotado {
    color: var(--q-negative);
  }
}

.ficha-pos__extra {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 14px;
  margin-top: 8px;
  font-size: 12px;
  color: var(--app-ink-2);

  span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }
}

.ficha-pos__lote--vencido {
  color: var(--q-negative);
}

.ficha-pos__lote--por_vencer {
  color: var(--q-warning);
}

.ficha-pos__vacio {
  margin: 0;
  padding: 16px;
  font-size: 13px;
  color: var(--app-ink-2);
}

@media (max-width: 599px) {
  .ficha-pos__cabecera {
    grid-template-columns: 1fr;
  }

  .ficha-pos__galeria {
    max-width: 220px;
  }

  .ficha-pos__fila {
    grid-template-columns: minmax(0, 1fr) auto;

    .ficha-pos__stock {
      grid-column: 1 / -1;
      text-align: left;
    }
  }
}
</style>
