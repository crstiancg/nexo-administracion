<template>
  <div class="app-list-page">
    <AppPageHeader
      :title="`Caja · ${userStore.sede?.nombre ?? 'sin sede'}`"
      :subtitle="subtitulo"
    >
      <template #actions>
        <!-- Para probar la ticketera (ancho, corte, letra) sin hacer una venta. -->
        <AppButton
          variant="tertiary"
          label="Ticket de prueba"
          icon="print"
          @click="impresionRef.imprimir(ticketDePrueba(userStore))"
        />
        <AppButton
          v-if="userStore.hasPermission('cajas.index')"
          variant="tertiary"
          label="Historial"
          icon="history"
          to="/cajas"
        />
        <template v-if="caja && !caja.vencida">
          <AppButton
            v-if="userStore.hasPermission('cajas.movimientos')"
            label="Ingreso"
            icon="add"
            @click="abrirMovimiento('ingreso')"
          />
          <AppButton
            v-if="userStore.hasPermission('cajas.movimientos')"
            label="Egreso"
            icon="remove"
            @click="abrirMovimiento('egreso')"
          />
        </template>
      </template>
    </AppPageHeader>

    <div
      v-if="cargando"
      class="caja__cargando"
    >
      <q-spinner size="28px" />
    </div>

    <!-- Abrir y cerrar se hace en el punto de venta: acá sólo se consulta. -->
    <AppCard
      v-else-if="!caja"
      class="caja__cerrada"
    >
      <div class="caja__icono">
        <q-icon
          name="lock"
          size="28px"
        />
      </div>
      <h2 class="caja__titulo">
        La caja está cerrada
      </h2>
      <p class="caja__texto">
        La caja se abre cada día desde el punto de venta, con el efectivo inicial del cajón,
        y se cierra ahí mismo con el arqueo al terminar la jornada.
      </p>
      <AppButton
        v-if="userStore.hasPermission('ventas.store')"
        variant="primary"
        label="Ir al punto de venta"
        icon="point_of_sale"
        to="/pos"
      />
    </AppCard>

    <template v-else>
      <div
        v-if="caja.vencida"
        class="caja__aviso"
        role="alert"
      >
        <q-icon
          name="warning"
          size="20px"
        />
        <span>
          Esta caja es de un día anterior y ya no cobra. Cerrala desde el punto de venta y abrí la de hoy.
        </span>
        <AppButton
          v-if="userStore.hasPermission('ventas.store')"
          variant="tertiary"
          label="Ir al POS"
          to="/pos"
        />
      </div>

      <CajaResumen :caja="caja" />
    </template>

    <AppDialog
      v-model="movimientoDialog"
      :title="tipoMovimiento === 'egreso' ? 'Registrar egreso' : 'Registrar ingreso'"
      persistent
    >
      <MovimientoCajaForm
        v-if="movimientoDialog"
        ref="movimientoRef"
        :tipo="tipoMovimiento"
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


    <ImpresionTicket ref="impresionRef" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppDialog from '@/components/AppDialog.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import CajaService from '@/services/CajaService'
import { useUserStore } from '@/stores/user-store'
import ImpresionTicket from '@/modules/Ventas/ImpresionTicket.vue'
import { ticketDePrueba } from '@/modules/Ventas/ticketDePrueba'
import CajaResumen from './CajaResumen.vue'
import MovimientoCajaForm from './MovimientoCajaForm.vue'

const $q = useQuasar()
const userStore = useUserStore()

const impresionRef = ref()
const caja = ref(null)
const cargando = ref(true)

const formatoFecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' })

const subtitulo = computed(() => {
  if (!caja.value) return 'Cerrada'
  if (caja.value.vencida) return `Abierta desde el ${formatoFecha.format(new Date(caja.value.abierta_at))}: falta cerrarla`
  const quien = caja.value.abierta_por?.name
  return `Abierta desde ${formatoFecha.format(new Date(caja.value.abierta_at))}${quien ? ` por ${quien}` : ''}`
})

async function cargar () {
  cargando.value = true
  try {
    caja.value = await CajaService.actual()
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

// ── Ingresos / egresos ──
const movimientoDialog = ref(false)
const movimientoRef = ref()
const tipoMovimiento = ref('ingreso')

function abrirMovimiento (tipo) {
  tipoMovimiento.value = tipo
  movimientoDialog.value = true
}

function movimientoGuardado (actualizada) {
  movimientoDialog.value = false
  caja.value = actualizada
  $q.notify({ type: 'positive', message: 'Movimiento registrado.', position: 'top-right', timeout: 1500 })
}
</script>

<style lang="scss" scoped>
.caja__cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}

.caja__cerrada {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  max-width: 480px;
  width: 100%;
  margin: 24px auto 0;
  padding: 40px 32px;
  text-align: center;
}

.caja__icono {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  border-radius: 999px;
  background: var(--app-brand-soft);
  color: var(--app-brand-soft-ink);
}

.caja__titulo {
  margin: 4px 0 0;
  font-size: 18px;
  font-weight: 700;
  color: var(--app-ink);
}

.caja__texto {
  margin: 0 0 8px;
  font-size: 14px;
  line-height: 1.6;
  color: var(--app-ink-2);
}

.caja__aviso {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border: 1px solid var(--app-negative-border);
  border-radius: 12px;
  background: var(--app-negative-soft);
  font-size: 14px;
  color: var(--app-ink);

  span {
    flex: 1;
  }
}
</style>
