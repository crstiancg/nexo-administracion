<template>
  <form
    class="proveedor-form"
    novalidate
    @submit.prevent="submit"
  >
    <div class="proveedor-form__ruc">
      <AppTextField
        v-model="form.proveedor.ruc"
        label="RUC"
        icon="badge"
        placeholder="20100085225"
        maxlength="11"
        inputmode="numeric"
        class="proveedor-form__grow"
        :error="form.errors[`${PATH}.ruc`]"
        autofocus
        @change="form.validate(`${PATH}.ruc`)"
      />
      <AppButton
        variant="secondary"
        label="Buscar en SUNAT"
        icon="travel_explore"
        :loading="consultando"
        :disable="!/^\d{11}$/.test(form.proveedor.ruc ?? '')"
        @click="consultar"
      />
    </div>
    <p
      v-if="aviso"
      class="proveedor-form__hint"
    >
      {{ aviso }}
    </p>

    <AppTextField
      v-model="form.proveedor.razon_social"
      label="Razón social"
      icon="business"
      maxlength="150"
      :error="form.errors[`${PATH}.razon_social`]"
      @change="form.validate(`${PATH}.razon_social`)"
    />

    <div class="proveedor-form__fila">
      <AppTextField
        v-model="form.proveedor.contacto"
        label="Contacto (opcional)"
        icon="person"
        placeholder="Nombre del vendedor"
        maxlength="100"
        class="proveedor-form__grow"
        :error="form.errors[`${PATH}.contacto`]"
      />
      <AppTextField
        v-model="form.proveedor.telefono"
        label="Teléfono (opcional)"
        icon="call"
        maxlength="30"
        class="proveedor-form__grow"
        :error="form.errors[`${PATH}.telefono`]"
      />
    </div>

    <AppTextField
      v-model="form.proveedor.email"
      label="Email (opcional)"
      icon="mail"
      type="email"
      maxlength="120"
      :error="form.errors[`${PATH}.email`]"
      @change="form.validate(`${PATH}.email`)"
    />

    <AppTextField
      v-model="form.proveedor.direccion"
      label="Dirección (opcional)"
      icon="place"
      maxlength="255"
      :error="form.errors[`${PATH}.direccion`]"
    />

    <q-toggle
      v-model="form.proveedor.activo"
      label="Activo (se le pueden cargar compras)"
      color="primary"
      class="proveedor-form__toggle"
    />

    <button
      type="submit"
      hidden
    />
  </form>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useForm } from 'laravel-precognition-vue'
import AppButton from '@/components/AppButton.vue'
import AppTextField from '@/components/AppTextField.vue'
import ProveedorService from '@/services/ProveedorService'

const PATH = 'proveedor'

const props = defineProps({
  // null = crear; con id = editar.
  id: {
    type: Number,
    default: null
  }
})

// `existente`: el RUC ya estaba cargado (alta rápida desde una compra).
const emit = defineEmits(['save', 'existente'])

// Función y no objeto: useForm guarda estos datos como "originales" para el reset().
const inicial = () => ({
  proveedor: { ruc: '', razon_social: '', contacto: '', telefono: '', email: '', direccion: '', activo: true }
})

const form = props.id
  ? useForm('put', `api/proveedores/${props.id}`, inicial)
  : useForm('post', 'api/proveedores', inicial)

onMounted(async () => {
  if (!props.id) return

  const p = await ProveedorService.get(props.id)
  form.setData({
    [PATH]: {
      ruc: p.ruc,
      razon_social: p.razon_social,
      contacto: p.contacto ?? '',
      telefono: p.telefono ?? '',
      email: p.email ?? '',
      direccion: p.direccion ?? '',
      activo: p.activo
    }
  })
})

// ── Autocompletar con SUNAT ──
const consultando = ref(false)
const aviso = ref('')

async function consultar () {
  consultando.value = true
  aviso.value = ''
  try {
    const r = await ProveedorService.consultarRuc(form.proveedor.ruc)
    if (r.origen === 'local') {
      if (props.id && r.proveedor.id === props.id) return
      aviso.value = `Ya está registrado como ${r.proveedor.razon_social}.`
      emit('existente', r.proveedor)
    } else if (r.origen === 'api') {
      form.proveedor.razon_social = r.datos.nombre
      if (r.datos.direccion) form.proveedor.direccion = r.datos.direccion
      aviso.value = 'Datos traídos de SUNAT.'
    } else {
      aviso.value = r.consulta_habilitada
        ? 'SUNAT no devolvió datos para ese RUC: completalo a mano.'
        : 'La consulta a SUNAT no está configurada: completalo a mano.'
    }
  } finally {
    consultando.value = false
  }
}

async function submit () {
  try {
    const respuesta = await form.submit()
    form.reset()
    emit('save', respuesta?.data)
  } catch {
    // 422: los errores quedan en form.errors y se ven debajo de cada campo.
  }
}

defineExpose({ form, submit })
</script>

<style lang="scss" scoped>
.proveedor-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.proveedor-form__ruc,
.proveedor-form__fila {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px;
}

.proveedor-form__grow {
  flex: 1 1 200px;
  min-width: 0;
}

.proveedor-form__hint {
  margin: -8px 0 0;
  font-size: 12px;
  color: var(--app-ink-2);
}

.proveedor-form__toggle {
  font-size: 13.5px;
  color: var(--app-ink);
}
</style>
