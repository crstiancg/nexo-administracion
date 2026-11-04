<template>
  <div class="app-list-page">
    <AppPageHeader
      title="Configuración"
      subtitle="Datos de la empresa, textos de los documentos, logo y mensajes de WhatsApp"
    >
      <template #actions>
        <AppButton
          variant="primary"
          label="Guardar cambios"
          icon="save"
          :loading="guardando"
          :disable="!cargado"
          @click="guardar"
        />
      </template>
    </AppPageHeader>

    <div
      v-if="!cargado"
      class="conf-cargando"
    >
      <q-spinner size="28px" />
    </div>

    <template v-else>
      <!-- ── Empresa ── -->
      <AppCard class="conf-card">
        <h2 class="conf-titulo">
          <q-icon name="business" /> Empresa
        </h2>
        <p class="conf-ayuda">
          Salen en el ticket, la proforma, la orden de servicio y los mensajes al cliente.
        </p>
        <div class="conf-grid">
          <AppTextField
            v-model="empresa.nombre"
            label="Razón social *"
            maxlength="120"
            :error="error('empresa.nombre')"
          />
          <AppTextField
            v-model="empresa.ruc"
            label="RUC"
            maxlength="11"
            inputmode="numeric"
            :error="error('empresa.ruc')"
          />
          <AppTextField
            v-model="empresa.direccion"
            label="Dirección"
            maxlength="200"
            :error="error('empresa.direccion')"
          />
          <AppTextField
            v-model="empresa.telefono"
            label="Teléfonos de contacto"
            maxlength="80"
            :error="error('empresa.telefono')"
          />
        </div>
      </AppCard>

      <!-- ── Logo ── -->
      <AppCard class="conf-card">
        <h2 class="conf-titulo">
          <q-icon name="image" /> Logo
        </h2>
        <div class="conf-logo">
          <div class="conf-logo__vista">
            <AppLogo :alto="56" />
          </div>
          <div class="conf-logo__acciones">
            <p class="conf-ayuda">
              PNG, JPG, WEBP o SVG, hasta 2 MB. Mejor con fondo transparente.
              <template v-if="!TIENDA.logo_url">
                Ahora se usa el logo de Nexo Technology.
              </template>
            </p>
            <input
              ref="inputLogo"
              type="file"
              accept="image/png,image/jpeg,image/webp,image/svg+xml"
              hidden
              @change="subirLogo"
            >
            <div class="conf-fila">
              <AppButton
                variant="secondary"
                :label="TIENDA.logo_url ? 'Cambiar logo' : 'Subir logo'"
                icon="upload"
                :loading="subiendoLogo"
                @click="inputLogo.click()"
              />
              <AppButton
                v-if="TIENDA.logo_url"
                variant="tertiary"
                label="Volver al de Nexo"
                @click="quitarLogo"
              />
            </div>
            <p
              v-if="errorLogo"
              class="conf-error"
            >
              {{ errorLogo }}
            </p>
          </div>
        </div>
      </AppCard>

      <!-- ── Documentos ── -->
      <AppCard class="conf-card">
        <h2 class="conf-titulo">
          <q-icon name="description" /> Textos de los documentos
        </h2>
        <AppTextField
          v-model="empresa.lema"
          label="Lema (bajo el logo en la proforma)"
          maxlength="300"
          :error="error('empresa.lema')"
        />
        <AppTextField
          v-model="empresa.garantia"
          label="Nota de garantía (proforma)"
          type="textarea"
          autogrow
          maxlength="500"
          :error="error('empresa.garantia')"
        />
        <AppTextField
          v-model="empresa.terminos_orden"
          label="Términos de la orden de servicio (sale impreso al recibir el equipo)"
          type="textarea"
          autogrow
          maxlength="1000"
          :error="error('empresa.terminos_orden')"
        />
        <AppTextField
          v-model="empresa.pie_ticket"
          label="Pie del ticket de venta"
          maxlength="200"
          :error="error('empresa.pie_ticket')"
        />
      </AppCard>

      <!-- ── WhatsApp ── -->
      <AppCard class="conf-card">
        <h2 class="conf-titulo">
          <q-icon name="mdi-whatsapp" /> Mensajes de WhatsApp
        </h2>
        <p class="conf-ayuda">
          El mensaje que se arma al avisarle al cliente según el estado de su orden. Toca una variable
          para agregarla; un mensaje vacío usa el de por defecto.
        </p>
        <div class="conf-variables">
          <button
            v-for="v in VARIABLES_WHATSAPP"
            :key="v.clave"
            type="button"
            class="conf-variable"
            :title="v.ayuda"
            @click="insertar(v.clave)"
            v-text="'{' + v.clave + '}'"
          />
        </div>

        <div class="conf-whatsapp">
          <div
            class="conf-estados"
            role="tablist"
          >
            <button
              v-for="(e, clave) in ESTADOS"
              :key="clave"
              type="button"
              role="tab"
              :aria-selected="String(estado === clave)"
              :class="['conf-estado', { 'conf-estado--activo': estado === clave }]"
              @click="estado = clave"
            >
              {{ e.label }}
              <span
                v-if="whatsapp[clave]"
                class="conf-estado__marca"
                title="Mensaje propio"
              />
            </button>
          </div>

          <div class="conf-editor">
            <q-input
              ref="editorRef"
              v-model="whatsapp[estado]"
              type="textarea"
              outlined
              autogrow
              :placeholder="PLANTILLAS_WHATSAPP[estado]"
              :error="Boolean(error(`whatsapp.${estado}`))"
              :error-message="error(`whatsapp.${estado}`)"
              class="conf-textarea"
            />
            <div class="conf-fila">
              <AppButton
                v-if="!whatsapp[estado]"
                variant="tertiary"
                label="Partir del mensaje por defecto"
                icon="edit_note"
                @click="whatsapp[estado] = PLANTILLAS_WHATSAPP[estado]"
              />
              <AppButton
                v-else
                variant="tertiary"
                label="Volver al de por defecto"
                icon="restart_alt"
                @click="whatsapp[estado] = ''"
              />
            </div>

            <!-- Así le llega al cliente (con datos de ejemplo). -->
            <div class="conf-preview">
              <span class="conf-preview__titulo">Vista previa</span>
              <div class="conf-preview__burbuja">
                {{ vistaPrevia }}
              </div>
            </div>
          </div>
        </div>
      </AppCard>
    </template>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import AppButton from '@/components/AppButton.vue'
import AppCard from '@/components/AppCard.vue'
import AppLogo from '@/components/AppLogo.vue'
import AppPageHeader from '@/components/AppPageHeader.vue'
import AppTextField from '@/components/AppTextField.vue'
import { aplicarConfiguracion, TIENDA } from '@/config/tienda'
import { ESTADOS, PLANTILLAS_WHATSAPP, rellenarPlantilla, VARIABLES_WHATSAPP, valoresWhatsapp } from '@/modules/OrdenesServicio/constantes'
import ConfiguracionService from '@/services/ConfiguracionService'

const $q = useQuasar()

const CAMPOS = ['nombre', 'ruc', 'direccion', 'telefono', 'lema', 'garantia', 'pie_ticket', 'terminos_orden']

const empresa = reactive(Object.fromEntries(CAMPOS.map((c) => [c, ''])))
const whatsapp = reactive(Object.fromEntries(Object.keys(ESTADOS).map((e) => [e, ''])))
const cargado = ref(false)

onMounted(async () => {
  const datos = await ConfiguracionService.get()
  CAMPOS.forEach((c) => { empresa[c] = datos[c] ?? '' })
  Object.keys(whatsapp).forEach((e) => { whatsapp[e] = datos.whatsapp?.[e] ?? '' })
  aplicarConfiguracion(datos)
  cargado.value = true
})

// ── Guardar ──
const guardando = ref(false)
const errores = ref({})

function error (campo) {
  return [].concat(errores.value[campo] ?? [])[0] ?? ''
}

async function guardar () {
  guardando.value = true
  errores.value = {}
  try {
    const datos = await ConfiguracionService.guardar({ empresa: { ...empresa }, whatsapp: { ...whatsapp } })
    aplicarConfiguracion(datos)
    $q.notify({ type: 'positive', message: 'Configuración guardada.', position: 'top-right', timeout: 1500 })
  } catch (e) {
    errores.value = e.response?.data?.errors ?? {}
    $q.notify({ type: 'negative', message: 'Revisa los campos marcados.', position: 'top-right' })
  } finally {
    guardando.value = false
  }
}

// ── Logo ──
const inputLogo = ref()
const subiendoLogo = ref(false)
const errorLogo = ref('')

async function subirLogo (evento) {
  const archivo = evento.target.files?.[0]
  evento.target.value = ''
  if (!archivo) return
  subiendoLogo.value = true
  errorLogo.value = ''
  try {
    aplicarConfiguracion(await ConfiguracionService.subirLogo(archivo))
  } catch (e) {
    errorLogo.value = Object.values(e.response?.data?.errors ?? {}).flat()[0] ?? 'No se pudo subir el logo.'
  } finally {
    subiendoLogo.value = false
  }
}

async function quitarLogo () {
  aplicarConfiguracion(await ConfiguracionService.quitarLogo())
}

// ── WhatsApp ──
const estado = ref('reparado')
const editorRef = ref()

// Agrega {variable} donde está el cursor (o al final).
async function insertar (clave) {
  const texto = whatsapp[estado.value] || PLANTILLAS_WHATSAPP[estado.value]
  const campo = editorRef.value?.getNativeElement?.()
  const pos = campo && whatsapp[estado.value] ? campo.selectionStart : texto.length
  whatsapp[estado.value] = `${texto.slice(0, pos)}{${clave}}${texto.slice(pos)}`
  await nextTick()
  campo?.focus()
}

const EJEMPLO = {
  codigo: 'OS-000012',
  estado: 'reparado',
  equipo: 'Laptop HP 250 G7',
  token: 'a'.repeat(32),
  total: '408.00',
  saldo: '208.00',
  garantia_hasta: '2026-12-03',
  cliente: { nombre: 'Ana Quispe' }
}

const vistaPrevia = computed(() => rellenarPlantilla(
  whatsapp[estado.value] || PLANTILLAS_WHATSAPP[estado.value],
  valoresWhatsapp({ ...EJEMPLO, estado: estado.value })
))
</script>

<style lang="scss" scoped>
.conf-cargando {
  display: flex;
  justify-content: center;
  padding: 48px;
}

.conf-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 20px;
}

.conf-titulo {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: var(--app-ink);
}

.conf-ayuda {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

.conf-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 14px;
}

.conf-fila {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.conf-error {
  margin: 0;
  font-size: 12.5px;
  color: var(--q-negative);
}

.conf-logo {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 20px;
}

.conf-logo__vista {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 200px;
  min-height: 96px;
  padding: 16px;
  border: 1px dashed var(--app-border-control);
  border-radius: 12px;
  background: var(--app-page);
}

.conf-logo__acciones {
  display: flex;
  flex: 1 1 240px;
  flex-direction: column;
  gap: 10px;
}

.conf-variables {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.conf-variable {
  padding: 3px 9px;
  border: 1px solid var(--app-border-control);
  border-radius: 999px;
  background: var(--app-surface);
  font-family: $font-mono;
  font-size: 12px;
  color: var(--app-brand-soft-ink);
  cursor: pointer;

  &:hover {
    background: var(--app-brand-soft);
  }
}

.conf-whatsapp {
  display: grid;
  grid-template-columns: 200px minmax(0, 1fr);
  gap: 16px;

  @media (max-width: 700px) {
    grid-template-columns: 1fr;
  }
}

.conf-estados {
  display: flex;
  flex-direction: column;
  gap: 4px;

  @media (max-width: 700px) {
    flex-direction: row;
    overflow-x: auto;
  }
}

.conf-estado {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 12px;
  border: 0;
  border-radius: 8px;
  background: none;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--app-ink-2);
  text-align: left;
  cursor: pointer;

  &:hover {
    background: var(--app-page);
  }

  &--activo {
    background: var(--app-brand-soft);
    color: var(--app-brand-soft-ink);
    font-weight: 700;
  }
}

.conf-estado__marca {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: var(--q-primary);
}

.conf-editor {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}

.conf-textarea :deep(.q-field__control) {
  border-radius: 10px;
}

.conf-preview {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border-radius: 12px;
  background: #E5DDD5;
}

.conf-preview__titulo {
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 0.4px;
  text-transform: uppercase;
  color: #54656F;
}

// La burbuja de WhatsApp (siempre clara: es cómo la ve el cliente).
.conf-preview__burbuja {
  align-self: flex-start;
  max-width: 420px;
  padding: 8px 10px;
  border-radius: 0 10px 10px 10px;
  background: #FFFFFF;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.12);
  font-size: 14px;
  line-height: 1.45;
  color: #111B21;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
</style>
