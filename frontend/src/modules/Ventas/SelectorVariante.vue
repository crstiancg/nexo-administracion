<template>
  <div class="selector">
    <!-- ── Foto grande: la de la presentación que se está mirando ── -->
    <figure class="selector__vista">
      <div
        class="selector__foto"
        :style="fotoVista ? { backgroundImage: `url(${fotoVista})` } : fondoSinFoto"
      >
        <q-icon
          v-if="!fotoVista"
          name="inventory_2"
          size="44px"
        />
      </div>
      <figcaption class="selector__pie">
        <div class="selector__nombre">
          {{ producto.nombre }}
        </div>
        <div
          v-if="producto.marca"
          class="selector__marca"
        >
          {{ producto.marca.nombre }}
        </div>
        <div
          v-if="producto.oferta"
          class="selector__oferta"
        >
          {{ producto.oferta.etiqueta }} · {{ producto.oferta.nombre }}
        </div>
      </figcaption>
    </figure>

    <!-- ── Presentaciones ── -->
    <div class="selector__lista">
      <p class="selector__ayuda">
        Tocá una presentación para sumar una unidad. Podés sumar varias antes de cerrar.
      </p>

      <button
        v-for="v in producto.variantes"
        :key="v.id"
        type="button"
        :class="['selector__item', {
          'selector__item--agotada': !disponible(v),
          'selector__item--flash': flash === v.id
        }]"
        :disabled="!disponible(v)"
        :aria-label="`${v.presentacion}${v.color ? `, ${v.color.nombre}` : ''}: ${v.stock} en stock, ${formatearPrecio(v.precio)}`"
        @mouseenter="vistaId = v.id"
        @focus="vistaId = v.id"
        @click="agregar(v)"
      >
        <span
          v-if="v.color"
          class="selector__swatch"
          :style="{ background: v.color.hexadecimal }"
        />
        <span class="selector__desc">
          <span class="selector__presentacion">{{ v.presentacion }}</span>
          <span class="selector__detalle">
            <template v-if="v.color">{{ v.color.nombre }} · </template>
            <span class="text-mono">{{ v.sku }}</span>
          </span>
        </span>
        <span class="selector__stock">
          <template v-if="v.stock">{{ v.stock }} {{ v.unidad?.abreviatura }}</template>
          <template v-else>Agotado</template>
        </span>
        <span class="selector__precios">
          <s
            v-if="enOferta(v)"
            class="selector__precioLista text-mono"
          >{{ formatearPrecio(v.precio_lista) }}</s>
          <span :class="['selector__precio', 'text-mono', { 'selector__precio--oferta': enOferta(v) }]">
            {{ formatearPrecio(v.precio) }}
          </span>
        </span>
        <span
          v-if="pos.cantidadDeVariante(v.id)"
          class="selector__enCarrito"
        >×{{ pos.cantidadDeVariante(v.id) }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { lineaDesdeCatalogo, usePosStore } from '@/stores/pos-store'
import { formatearPrecio } from '@/utils/moneda'
import { beepError, beepOk } from '@/utils/sonido'

/**
 * Las presentaciones de un producto (cartucho, galón, balde…) con su stock y
 * su precio de hoy, para elegir cuál se vende. Es el equivalente del
 * "¿unidad o blíster?" de sistema-botica.
 */
const props = defineProps({
  // CatalogoProductoResource.
  producto: {
    type: Object,
    required: true
  }
})

const pos = usePosStore()

// ── Foto ──
// El catálogo manda, por presentación, su foto o (si no tiene) la del
// producto. Arranca en la primera con stock.
const vistaId = ref(props.producto.variantes.find((v) => v.stock > 0)?.id ?? props.producto.variantes[0]?.id ?? null)
const fotoVista = computed(() =>
  props.producto.variantes.find((v) => v.id === vistaId.value)?.miniatura_url ?? props.producto.miniatura_url)

// Sin ninguna foto: el mismo degradé que la tarjeta del catálogo.
const fondoSinFoto = computed(() => {
  const tono = ((props.producto.nombre || 'A').charCodeAt(0) * 23) % 360
  return { background: `linear-gradient(180deg, hsl(${tono} 60% 96%), hsl(${tono} 45% 90%))` }
})

// ── Agregar ──
// Queda stock después de lo que ya está en el carrito.
function disponible (variante) {
  return variante.stock - pos.cantidadDeVariante(variante.id) > 0
}

function enOferta (variante) {
  return Number(variante.precio) < Number(variante.precio_lista)
}

// Destello en la fila recién sumada.
const flash = ref(null)
let flashTimer

function agregar (variante) {
  const resultado = pos.agregar(lineaDesdeCatalogo(props.producto, variante))
  if (resultado === 'ok') {
    beepOk()
    flash.value = variante.id
    clearTimeout(flashTimer)
    flashTimer = setTimeout(() => { flash.value = null }, 350)
  } else {
    beepError()
  }
}
</script>

<style lang="scss" scoped>
.selector {
  display: grid;
  grid-template-columns: 220px minmax(0, 1fr);
  align-items: start;
  gap: 20px;

  @media (max-width: 699px) {
    grid-template-columns: 1fr;
  }
}

// ── Vista grande ──
.selector__vista {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;

  @media (max-width: 699px) {
    flex-direction: row;
    align-items: center;
  }
}

.selector__foto {
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1 / 1;
  border: 1px solid var(--app-border-subtle);
  border-radius: 14px;
  background-position: center;
  background-size: cover;
  color: rgba(0, 0, 0, 0.25);
  transition: background-image 0.2s ease;

  @media (max-width: 699px) {
    flex: 0 0 96px;
  }
}

.selector__nombre {
  font-size: 16px;
  font-weight: 700;
  line-height: 1.3;
  color: var(--app-ink);
}

.selector__marca {
  margin-top: 2px;
  font-size: 13px;
  color: var(--app-ink-2);
}

.selector__oferta {
  display: inline-block;
  margin-top: 4px;
  padding: 2px 8px;
  border-radius: 6px;
  background: #DC2626;
  font-size: 12px;
  font-weight: 700;
  color: #FFFFFF;
}

// ── Lista ──
.selector__lista {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}

.selector__ayuda {
  margin: 0 0 2px;
  font-size: 12px;
  color: var(--app-ink-2);
}

.selector__item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  min-height: 56px;
  padding: 8px 14px;
  border: 1px solid var(--app-border-control);
  border-radius: 12px;
  background: var(--app-surface);
  text-align: left;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;

  &:hover:not(:disabled) {
    border-color: $primary;
    background: rgba($primary, 0.06);
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
  }

  &--agotada {
    cursor: not-allowed;
    opacity: 0.45;
  }

  &--flash {
    border-color: $primary;
    background: rgba($primary, 0.18);
  }
}

.selector__swatch {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
  border: 1px solid var(--app-border-control);
  border-radius: 50%;
}

.selector__desc {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.selector__presentacion {
  font-size: 14px;
  font-weight: 600;
  color: var(--app-ink);
}

.selector__detalle {
  overflow: hidden;
  font-size: 12px;
  white-space: nowrap;
  text-overflow: ellipsis;
  color: var(--app-ink-2);
}

.selector__stock {
  flex-shrink: 0;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--app-ink-2);
}

.selector__precios {
  display: flex;
  flex-shrink: 0;
  flex-direction: column;
  align-items: flex-end;
  min-width: 76px;
}

.selector__precioLista {
  font-size: 11px;
  color: var(--app-ink-2);
}

.selector__precio {
  font-size: 14px;
  font-weight: 700;
  color: var(--app-ink);

  &--oferta {
    color: #DC2626;
  }
}

.selector__enCarrito {
  position: absolute;
  top: -7px;
  right: -7px;
  min-width: 22px;
  padding: 1px 6px;
  border-radius: 999px;
  background: $primary;
  font-size: 11px;
  font-weight: 700;
  color: #FFFFFF;
}
</style>
