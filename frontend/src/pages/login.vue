<template>
  <div class="auth">
    <!-- Franja de arriba (en Bata van sus marcas): lo que hace Nexo. -->
    <div class="auth__strip">
      <span
        v-for="s in SERVICIOS"
        :key="s"
      >{{ s }}</span>
      <span class="auth__stripLugar">
        <q-icon
          name="place"
          size="14px"
        />
        Puno - Perú
      </span>
    </div>

    <!-- Barra superior como en la tienda: logo al centro, nada más. -->
    <header class="auth__bar">
      <AppLogo :alto="40" />
      <div class="auth__theme">
        <SwitchDarkMode />
      </div>
    </header>

    <div class="auth__body">
      <main class="auth__panel">
        <header
          class="auth__head"
          style="--rise: 0"
        >
          <h1 class="auth__title">Ingresa a tu cuenta</h1>
          <p class="auth__subtitle">
            ¡Bienvenido! Ingresa con el usuario y la contraseña que te dio tu
            administrador. Si no la recuerdas, haz clic en
            "¿Olvidaste tu contraseña?".
          </p>
        </header>

        <!-- <form> nativo y no QForm: la validación es de dos campos
             obligatorios y la resolvemos acá. -->
        <form
          class="auth__fields"
          novalidate
          style="--rise: 1"
          @submit.prevent="onSubmit"
        >
          <AppTextField
            v-model="username"
            size="lg"
            label="Usuario *"
            placeholder="tu.usuario"
            autocomplete="username"
            autofocus
            :error="errors.username"
          />

          <AppTextField
            v-model="password"
            size="lg"
            type="password"
            label="Contraseña *"
            autocomplete="current-password"
            :error="errors.password"
          />

          <div class="auth__row">
            <q-checkbox
              v-model="remember"
              dense
              size="sm"
              label="Recuérdame"
              class="auth__remember"
            />

            <!-- <button> y no <a>: no navega, despliega una aclaración. -->
            <button
              type="button"
              class="auth__link"
              @click="hintOpen = !hintOpen"
            >
              ¿Olvidaste tu contraseña?
            </button>
          </div>

          <p
            v-if="hintOpen"
            class="auth__hint"
          >
            <q-icon
              name="info_outline"
              size="15px"
            />
            Todavía no hay recuperación automática. Pídele a tu administrador
            que restablezca tu contraseña.
          </p>

          <!-- El error NUNCA va relleno de marca: tinte + contorno + ícono + la palabra. -->
          <p
            v-if="formError"
            class="auth__error"
            role="alert"
          >
            <q-icon
              name="error_outline"
              size="17px"
              class="auth__errorIcon"
            />
            <span>{{ formError }}</span>
          </p>

          <AppButton
            variant="primary"
            type="submit"
            class="auth__submit"
            :loading="pending"
            :disable="pending"
          >
            Iniciar sesión
          </AppButton>
        </form>
      </main>

      <!-- Informativa: lo que hay del otro lado de la puerta. -->
      <aside
        class="auth__info"
        style="--rise: 2"
      >
        <h2 class="auth__infoTitle">
          Todo Nexo en un solo lugar
        </h2>
        <p class="auth__infoText">
          El panel de {{ TIENDA.nombre }} para el equipo de tienda y taller.
        </p>
        <ul class="auth__modulos">
          <li
            v-for="m in MODULOS"
            :key="m.icon"
          >
            <span class="auth__modIcon">
              <q-icon
                :name="m.icon"
                size="20px"
              />
            </span>
            {{ m.texto }}
          </li>
        </ul>
        <div class="auth__contacto">
          <q-icon
            name="support_agent"
            size="18px"
          />
          Soporte: {{ TIENDA.telefono }}
        </div>
      </aside>
    </div>

    <!-- Pie como el de la tienda: la marca grande y los datos de contacto. -->
    <footer class="auth__pie">
      <div class="auth__pieGrid">
        <div>
          <div class="auth__pieMarca">
            Nexo Technology
          </div>
          <p class="auth__pieLema">
            Tecnología que te conecta. Innovación al alcance de todos.
          </p>
        </div>
        <div>
          <h3 class="auth__pieTitulo">
            Visítanos
          </h3>
          <p>{{ TIENDA.direccion }}</p>
        </div>
        <div>
          <h3 class="auth__pieTitulo">
            Contáctanos
          </h3>
          <p>{{ TIENDA.telefono }}</p>
        </div>
        <div>
          <h3 class="auth__pieTitulo">
            Empresa
          </h3>
          <p>{{ TIENDA.nombre }}</p>
          <p>RUC {{ TIENDA.ruc }}</p>
        </div>
      </div>
      <div class="auth__pieBase">
        <span>© {{ anio }} {{ TIENDA.nombre }}</span>
        <span class="auth__pieSeguro">
          <q-icon
            name="lock"
            size="13px"
          />
          Conexión cifrada
        </span>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppTextField from '@/components/AppTextField.vue'
import AppLogo from '@/components/AppLogo.vue'
import SwitchDarkMode from '@/components/SwitchDarkMode.vue'
import { TIENDA } from '@/config/tienda'
import { useUserStore, AuthError } from '@/stores/user-store'

const SERVICIOS = ['Venta de equipos', 'Servicio técnico', 'Soluciones tecnológicas', 'Soporte especializado']
const anio = new Date().getFullYear()

// Lo que se hace en el sistema: la columna de la derecha (estilo Bata).
const MODULOS = [
  { icon: 'build', texto: 'Órdenes de servicio con seguimiento por QR y avisos por WhatsApp' },
  { icon: 'point_of_sale', texto: 'Ventas, cotizaciones y comprobantes electrónicos SUNAT' },
  { icon: 'account_balance_wallet', texto: 'Caja, créditos y ganancias siempre al día' },
  { icon: 'inventory_2', texto: 'Inventario por sede, compras y traslados' }
]

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

/**
 * A dónde volver después de entrar. Sólo rutas internas: "//otro.com" es una
 * URL absoluta sin protocolo y convertiría al login en un redirector abierto.
 */
function destination () {
  const target = route.query.redirectTo
  return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//')
    ? target
    : '/'
}

const username = ref('')
const password = ref('')
const remember = ref(false)

const pending = ref(false)
const hintOpen = ref(false)

// Errores por campo (debajo de cada uno) y del formulario (el aviso de
// arriba del botón). Son cosas distintas: el primero dice qué te falta
// completar, el segundo qué respondió el servidor.
const errors = reactive({ username: '', password: '' })
const formError = ref('')

function validate () {
  errors.username = username.value.trim() ? '' : 'Ingresa tu usuario.'
  errors.password = password.value ? '' : 'Ingresa tu contraseña.'
  return !errors.username && !errors.password
}

async function onSubmit () {
  // Un error de la respuesta anterior tiene que irse al reintentar: dejarlo
  // en pantalla mientras corre el nuevo intento se lee como si acabara de
  // fallar otra vez.
  formError.value = ''

  if (!validate()) return

  pending.value = true

  try {
    await userStore.login({
      username: username.value,
      password: password.value,
      remember: remember.value
    })

    router.replace(destination())
  } catch (error) {
    // Sólo un AuthError trae un mensaje pensado para mostrar. Cualquier otra
    // cosa (red caída, bug nuestro) sale con un texto genérico: el `message`
    // crudo de una excepción no es algo que el usuario pueda accionar.
    formError.value = error instanceof AuthError
      ? error.message
      : 'No pudimos conectarnos. Revisa tu conexión e intenta de nuevo.'

    password.value = ''
  } finally {
    pending.value = false
  }
}
</script>

<style lang="scss" scoped>
// ── Composición (calcada del acceso de Bata) ─────────────────────────────
// Barra blanca arriba con el logo al centro; abajo dos columnas de ~430px
// separadas por una línea fina: el formulario y una columna informativa.
.auth {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
  background: var(--app-surface);
}

.auth__strip {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 28px;
  height: 32px;
  padding: 0 24px;
  background: var(--app-page);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  color: var(--app-ink-2);
}

.auth__stripLugar {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--q-primary);
}

.auth__bar {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 72px;
  border-bottom: 1px solid var(--app-border-subtle);
}

.auth__theme {
  position: absolute;
  top: 50%;
  right: 24px;
  transform: translateY(-50%);
}

.auth__body {
  flex: 1;
  display: grid;
  grid-template-columns: minmax(0, 430px) minmax(0, 430px);
  justify-content: center;
  align-items: start;
  padding: 64px 24px 80px;
}

.auth__panel {
  padding-right: 64px;
  border-right: 1px solid var(--app-border-control);
}

.auth__info {
  padding-left: 64px;
}

// ── Encabezado ──
.auth__head {
  margin-bottom: 26px;
}

.auth__title {
  margin: 0 0 10px;
  font-size: 24px;
  font-weight: 700;
  letter-spacing: 0.25px;
  line-height: 30px;
  text-transform: uppercase;
  color: var(--app-ink);
}

.auth__subtitle {
  margin: 0;
  font-size: 15px;
  line-height: 22px;
  color: var(--app-ink);
}

// ── Formulario ──
.auth__fields {
  display: flex;
  flex-direction: column;
  gap: 16px;

  // Campos rectos con borde gris cálido, como los de la tienda.
  :deep(.app-field .q-field__control) {
    border-radius: 2px;
  }

  :deep(.app-field .q-field__control):before {
    border-color: var(--app-ink-2);
  }

  // El autocompletado de Chrome pinta el input de celeste y en oscuro se ve
  // un parche claro: se tapa con una sombra interna del color del campo.
  :deep(input:-webkit-autofill) {
    -webkit-box-shadow: 0 0 0 1000px var(--app-surface) inset;
    -webkit-text-fill-color: var(--app-ink);
    caret-color: var(--app-ink);
    transition: background-color 9999s ease-out;
  }

  :deep(.app-field .app-field__label) {
    font-size: 13px;
    font-weight: 400;
    letter-spacing: 0.2px;
  }
}

.auth__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.auth__remember {
  font-size: 15px;
  color: var(--app-ink);
}

.auth__link {
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  font-size: 15px;
  color: var(--app-ink);
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;

  &:hover {
    color: var(--q-primary);
  }

  &:focus-visible {
    outline: 2px solid var(--q-primary);
    outline-offset: 2px;
  }
}

.auth__hint {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 2px;
  background: var(--app-page);
  border: 1px solid var(--app-border-subtle);
  font-size: 12.5px;
  line-height: 1.45;
  color: var(--app-ink-2);
}

.auth__error {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  margin: 0;
  padding: 11px 13px;
  border-radius: 2px;
  background: var(--app-negative-soft);
  border: 1px solid var(--app-negative-border);
  color: var(--app-ink-negative);
  font-size: 13px;
  line-height: 1.4;
}

.auth__errorIcon {
  flex-shrink: 0;
  margin-top: 1px;
}

// Botón sólido, recto, texto chico en mayúsculas espaciadas.
.auth__submit {
  width: 100%;
  height: 48px;
  margin-top: 16px;
  border-radius: 2px;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 1.2px;
  text-transform: uppercase;
}

// Negro como el de la tienda. Con los tokens de tinta y superficie se
// invierte solo en oscuro (botón claro con letras oscuras) y no se pierde
// sobre el fondo negro. La doble clase le gana al relleno de AppButton.
.auth__submit.app-btn--primary {
  background: var(--app-ink);
  color: var(--app-surface);
  box-shadow: none;

  &:hover {
    background: var(--app-ink);
    opacity: 0.86;
  }
}

// ── Columna informativa ──
.auth__infoTitle {
  margin: 0 0 10px;
  font-size: 24px;
  font-weight: 700;
  line-height: 30px;
  color: var(--app-ink);
}

.auth__infoText {
  margin: 0 0 30px;
  font-size: 15px;
  line-height: 22px;
  color: var(--app-ink);
}

.auth__modulos {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin: 0 0 30px;
  padding: 0;
  list-style: none;

  li {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 15px;
    line-height: 22px;
    color: var(--app-ink);
  }
}

// Ícono en círculo rojo con anillo, como los beneficios de la tienda.
.auth__modIcon {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--q-primary);
  box-shadow: 0 0 0 2px var(--app-surface), 0 0 0 3.5px var(--q-primary);
  color: var(--app-on-primary);
}

.auth__contacto {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 48px;
  border: 1px solid var(--app-ink);
  border-radius: 2px;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.6px;
  color: var(--app-ink);
}

// ── Pie ──
.auth__pie {
  padding: 48px 24px 0;
  background: var(--app-page);
  color: var(--app-ink);
}

.auth__pieGrid {
  display: grid;
  grid-template-columns: 1.4fr 1fr 1fr 1fr;
  gap: 32px;
  max-width: 1100px;
  margin: 0 auto;
  padding-bottom: 40px;

  p {
    margin: 0 0 4px;
    font-size: 14px;
    line-height: 1.5;
  }
}

// La marca grande y roja, como el "BATA CLUB" del pie de la tienda.
.auth__pieMarca {
  font-family: $font-display;
  font-size: 40px;
  font-weight: 700;
  line-height: 1;
  text-transform: uppercase;
  color: var(--q-primary);
}

.auth__pieLema {
  margin-top: 10px !important;
  color: var(--app-ink-2);
}

.auth__pieTitulo {
  margin: 0 0 12px;
  font-size: 12px;
  font-weight: 500;
  line-height: 1.4;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--app-ink-2);
}

.auth__pieBase {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  max-width: 1100px;
  margin: 0 auto;
  padding: 16px 0 20px;
  border-top: 1px solid var(--app-border-control);
  font-size: 12px;
  color: var(--app-ink-2);
}

.auth__pieSeguro {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

// ── Movimiento ──
@media (prefers-reduced-motion: no-preference) {
  .auth__head,
  .auth__fields,
  .auth__info {
    animation: auth-rise 0.5s cubic-bezier(0.22, 1, 0.36, 1) backwards;
    animation-delay: calc(80ms + var(--rise, 0) * 80ms);
  }
}

@keyframes auth-rise {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
}

// ── Una columna ──
// La informativa baja debajo del formulario y la línea pasa a ser horizontal.
@media (max-width: 959px) {
  .auth__body {
    grid-template-columns: minmax(0, 460px);
    padding: 40px 20px 56px;
  }

  .auth__panel {
    padding: 0 0 40px;
    border-right: 0;
    border-bottom: 1px solid var(--app-border-control);
  }

  .auth__info {
    padding: 40px 0 0;
  }

  .auth__strip span:not(.auth__stripLugar) {
    display: none;
  }

  .auth__pieGrid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 599px) {
  .auth__bar {
    height: 60px;
  }

  .auth__theme {
    right: 14px;
  }

  .auth__title,
  .auth__infoTitle {
    font-size: 21px;
  }

  .auth__pieGrid {
    grid-template-columns: 1fr;
    gap: 24px;
  }

  .auth__pieBase {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
