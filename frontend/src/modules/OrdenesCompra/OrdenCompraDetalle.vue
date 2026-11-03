<template>
  <div
    v-if="orden"
    class="oc-detalle"
  >
    <header class="oc-detalle__cabecera">
      <div>
        <div class="oc-detalle__codigo text-mono">
          {{ orden.codigo }}
        </div>
        <div class="oc-detalle__meta">
          {{ fechaCorta(orden.fecha) }} · {{ orden.sede?.nombre }}
          <template v-if="orden.usuario">
            · {{ orden.usuario.name }}
          </template>
        </div>
      </div>
      <AppChip
        :status="ESTADOS[orden.estado].status"
        :label="ESTADOS[orden.estado].label"
      />
    </header>

    <p
      v-if="orden.compra"
      class="oc-detalle__aviso"
    >
      Se recibió con la compra <strong class="text-mono">{{ orden.compra.codigo }}</strong>: la mercadería ya está en stock.
    </p>
    <p
      v-else-if="orden.estado === 'anulada'"
      class="oc-detalle__aviso"
    >
      Anulado el {{ fechaCorta(orden.anulada_at) }}<template v-if="orden.motivo_anulacion">
        : {{ orden.motivo_anulacion }}
      </template>
    </p>
    <p
      v-else-if="orden.estado === 'enviada'"
      class="oc-detalle__aviso"
    >
      Enviado al proveedor el {{ fechaCorta(orden.enviada_at) }}. Cuando llegue la mercadería, registrá la recepción.
    </p>

    <!-- La misma vista que se imprime. -->
    <div class="oc-detalle__papel">
      <OrdenCompraDocumento :orden="orden" />
    </div>

    <p
      v-if="orden.observacion"
      class="oc-detalle__aviso"
    >
      <strong>Nota interna:</strong> {{ orden.observacion }}
    </p>
  </div>

  <div
    v-else
    class="oc-detalle__cargando"
  >
    <q-spinner size="24px" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import AppChip from '@/components/AppChip.vue'
import OrdenCompraService from '@/services/OrdenCompraService'
import OrdenCompraDocumento from './OrdenCompraDocumento.vue'
import { ESTADOS, fechaCorta } from './constantes'

const props = defineProps({
  id: {
    type: Number,
    required: true
  }
})

const orden = ref(null)

onMounted(async () => {
  orden.value = await OrdenCompraService.get(props.id)
})

// Las acciones las dispara la lista (dueña de los diálogos); acá se refresca.
function actualizar (nueva) {
  orden.value = nueva
}

defineExpose({ orden, actualizar })
</script>

<style lang="scss" scoped>
.oc-detalle {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.oc-detalle__cabecera {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.oc-detalle__codigo {
  font-size: 18px;
  font-weight: 700;
  color: var(--app-ink);
}

.oc-detalle__meta {
  font-size: 12px;
  color: var(--app-ink-2);
}

.oc-detalle__aviso {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--app-border-subtle);
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink);
}

// Vista previa del papel: siempre blanca, también en tema oscuro.
.oc-detalle__papel {
  overflow-x: auto;
  padding: 20px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 8px;
  background: #FFFFFF;
}

.oc-detalle__cargando {
  display: flex;
  justify-content: center;
  padding: 32px;
}
</style>
