<template>
  <AppDialog
    v-model="abierto"
    title="Abrir caja"
  >
    <p class="caja-ingreso__texto">
      No hay caja abierta en <strong>{{ sede }}</strong>. Ábrela para empezar a cobrar.
    </p>
    <AbrirCajaForm @save="abierta" />

    <template #actions>
      <AppButton
        variant="tertiary"
        label="Ahora no"
        @click="abierto = false"
      />
    </template>
  </AppDialog>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppDialog from '@/components/AppDialog.vue'
import CajaService from '@/services/CajaService'
import { useUserStore } from '@/stores/user-store'
import AbrirCajaForm from './AbrirCajaForm.vue'

/**
 * Justo después de iniciar sesión, a quien tiene el permiso
 * cajas.abrir-al-ingresar (Roles), le pide abrir la caja de su sede si no
 * hay una abierta. Se puede dejar para después: la caja se abre igual desde
 * el punto de venta.
 */
const $q = useQuasar()
const userStore = useUserStore()

const abierto = ref(false)
const sede = ref('')

onMounted(async () => {
  if (!userStore.recienIngreso) return
  // Una vez por inicio de sesión, aunque no tenga el permiso.
  userStore.recienIngreso = false
  if (!userStore.hasPermission('cajas.abrir-al-ingresar') || !userStore.hasPermission('cajas.abrir')) return

  try {
    const respuesta = await CajaService.alIngresar()
    sede.value = respuesta.sede ?? ''
    abierto.value = respuesta.abrir
  } catch {
    // Un aviso de bienvenida no vale un error en pantalla.
  }
})

function abierta () {
  abierto.value = false
  $q.notify({ type: 'positive', message: `Caja abierta en ${sede.value}.`, position: 'top-right', timeout: 2000 })
}
</script>

<style lang="scss" scoped>
.caja-ingreso__texto {
  margin: 0 0 16px;
  font-size: 14px;
  line-height: 1.55;
  color: var(--app-ink-2);

  strong {
    color: var(--app-ink);
  }
}
</style>
