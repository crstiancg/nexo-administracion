<template>
  <div
    v-if="cotizacion"
    class="cot-detalle"
  >
    <header class="cot-detalle__cabecera">
      <div>
        <div class="cot-detalle__codigo text-mono">
          {{ cotizacion.codigo }}
        </div>
        <div class="cot-detalle__meta">
          {{ fechaCorta(cotizacion.fecha) }} · válida hasta {{ fechaCorta(cotizacion.valida_hasta) }}
          · {{ cotizacion.sede?.nombre }}
          <template v-if="cotizacion.usuario">
            · {{ cotizacion.usuario.name }}
          </template>
        </div>
      </div>
      <AppChip
        :status="ESTADOS[cotizacion.estado].status"
        :label="ESTADOS[cotizacion.estado].label"
      />
    </header>

    <p
      v-if="cotizacion.pedido"
      class="cot-detalle__aviso"
    >
      Se convirtió en el pedido <strong class="text-mono">{{ cotizacion.pedido.codigo }}</strong>:
      se confirma, cobra y entrega desde Pedidos.
    </p>

    <!-- La misma vista que se imprime. -->
    <div class="cot-detalle__papel">
      <CotizacionDocumento :cotizacion="cotizacion" />
    </div>

    <p
      v-if="cotizacion.observacion"
      class="cot-detalle__nota"
    >
      <strong>Nota interna:</strong> {{ cotizacion.observacion }}
    </p>
  </div>

  <div
    v-else
    class="cot-detalle__cargando"
  >
    <q-spinner size="24px" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import AppChip from '@/components/AppChip.vue'
import CotizacionService from '@/services/CotizacionService'
import CotizacionDocumento from './CotizacionDocumento.vue'
import { ESTADOS, fechaCorta } from './constantes'

const props = defineProps({
  id: {
    type: Number,
    required: true
  }
})

const cotizacion = ref(null)

onMounted(async () => {
  cotizacion.value = await CotizacionService.get(props.id)
})

// Las acciones las dispara la lista (dueña de los diálogos); acá se refresca.
function actualizar (nueva) {
  cotizacion.value = nueva
}

defineExpose({ cotizacion, actualizar })
</script>

<style lang="scss" scoped>
.cot-detalle {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.cot-detalle__cabecera {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.cot-detalle__codigo {
  font-size: 18px;
  font-weight: 700;
  color: var(--app-ink);
}

.cot-detalle__meta {
  font-size: 12px;
  color: var(--app-ink-2);
}

.cot-detalle__aviso,
.cot-detalle__nota {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--app-border-subtle);
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink);
}

// Vista previa del papel: siempre blanca, también en tema oscuro.
.cot-detalle__papel {
  overflow-x: auto;
  padding: 20px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 8px;
  background: #FFFFFF;
}

.cot-detalle__cargando {
  display: flex;
  justify-content: center;
  padding: 32px;
}
</style>
