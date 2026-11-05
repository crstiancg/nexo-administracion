<template>
  <main class="proforma">
    <div
      v-if="cargando"
      class="proforma__centro"
    >
      <q-spinner size="28px" />
    </div>

    <div
      v-else-if="!cotizacion"
      class="proforma__centro proforma__vacio"
    >
      <q-icon
        name="search_off"
        size="40px"
      />
      <p>No encontramos esta cotización. Revise el enlace o comuníquese con nosotros.</p>
    </div>

    <template v-else>
      <header class="proforma__barra">
        <div class="proforma__titulo">
          <strong>Proforma {{ cotizacion.codigo }}</strong>
          <span>{{ TIENDA.nombre }}</span>
        </div>
        <div class="proforma__acciones">
          <AppButton
            variant="primary"
            icon="print"
            label="Imprimir / PDF"
            @click="impresionRef.imprimir(cotizacion)"
          />
          <AppButton
            v-if="consultar"
            variant="secondary"
            icon="mdi-whatsapp"
            label="Consultar"
            :href="consultar"
            target="_blank"
            rel="noopener"
          />
        </div>
      </header>

      <p
        v-if="aviso"
        class="proforma__aviso"
        role="status"
      >
        <q-icon name="info" /> {{ aviso }}
      </p>

      <!-- La hoja A4 tal cual; en el celular se achica para entrar entera. -->
      <div
        ref="marcoRef"
        class="proforma__marco"
      >
        <div
          class="proforma__hoja"
          :style="{ zoom: escala }"
        >
          <CotizacionDocumento :cotizacion="cotizacion" />
        </div>
      </div>

      <ImpresionCotizacion ref="impresionRef" />
    </template>
  </main>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import { aplicarConfiguracion, TIENDA } from '@/config/tienda'
import CotizacionDocumento from '@/modules/Cotizaciones/CotizacionDocumento.vue'
import ImpresionCotizacion from '@/modules/Cotizaciones/ImpresionCotizacion.vue'
import { fechaCorta, numeroWhatsapp } from '@/modules/Cotizaciones/constantes'
import CotizacionService from '@/services/CotizacionService'

// Página pública (el enlace que le llega al cliente por WhatsApp): sin
// sesión ni menú. Muestra la proforma y la deja imprimir o guardar en PDF.

const route = useRoute()
const cotizacion = ref(null)
const cargando = ref(true)
const impresionRef = ref()

const aviso = computed(() => {
  switch (cotizacion.value?.estado) {
    case 'vencida':
      return `Esta cotización venció el ${fechaCorta(cotizacion.value.valida_hasta)}: los precios pueden haber cambiado. Consúltenos para actualizarla.`
    case 'convertida':
      return 'Esta cotización ya fue aceptada. ¡Gracias por su confianza!'
    case 'rechazada':
      return 'Esta cotización fue anulada. Consúltenos si necesita una nueva.'
    default:
      return ''
  }
})

/** WhatsApp de la tienda (el de la sede o el de la empresa) con la consulta escrita. */
const consultar = computed(() => {
  const numero = numeroWhatsapp(cotizacion.value?.sede?.telefono) ?? numeroWhatsapp(TIENDA.telefono)
  if (!numero) return null
  const texto = `Hola, tengo una consulta sobre la cotización ${cotizacion.value.codigo}.`
  return `https://wa.me/${numero}?text=${encodeURIComponent(texto)}`
})

// ── Escala de la hoja ──
const ANCHO_HOJA = 794 // 210 mm a 96 dpi
const marcoRef = ref()
const escala = ref(1)
let observador = null

function medir () {
  const ancho = marcoRef.value?.clientWidth ?? ANCHO_HOJA
  escala.value = Math.min(1, ancho / ANCHO_HOJA)
}

onMounted(async () => {
  try {
    const { empresa, cotizacion: datos } = await CotizacionService.proforma(route.params.token)
    aplicarConfiguracion(empresa)
    cotizacion.value = datos
    document.title = `Proforma ${datos.codigo} · ${TIENDA.nombre}`
  } catch {
    cotizacion.value = null
  } finally {
    cargando.value = false
  }

  await nextTick()
  if (marcoRef.value) {
    observador = new ResizeObserver(medir)
    observador.observe(marcoRef.value)
    medir()
  }
})

onBeforeUnmount(() => observador?.disconnect())
</script>

<style lang="scss" scoped>
.proforma {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  min-height: 100vh;
  padding: 20px 12px 32px;
  background: var(--app-page);
  color: var(--app-ink);
}

.proforma__centro {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  flex: 1;
  text-align: center;
}

.proforma__vacio {
  max-width: 360px;
  color: var(--app-ink-2);
}

.proforma__barra {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  max-width: 842px;
}

.proforma__titulo {
  display: flex;
  flex-direction: column;
  gap: 2px;

  strong {
    font-size: 17px;
  }

  span {
    font-size: 12.5px;
    color: var(--app-ink-2);
  }
}

.proforma__acciones {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.proforma__aviso {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  width: 100%;
  max-width: 842px;
  margin: 0;
  padding: 10px 14px;
  border: 1px solid color-mix(in srgb, var(--q-warning) 45%, transparent);
  border-radius: 10px;
  background: color-mix(in srgb, var(--q-warning) 12%, transparent);
  font-size: 13px;
}

.proforma__marco {
  width: 100%;
  max-width: 794px;
}

// La hoja blanca con su margen, como sale impresa.
.proforma__hoja {
  width: 794px;
  padding: 48px;
  border-radius: 6px;
  background: #FFFFFF;
  box-shadow: var(--app-shadow-card);
}
</style>
