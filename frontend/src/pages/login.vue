<template>
  <div class="auth">
    <AuthShowcase
      class="auth__showcase"
      :photo="loginHero"
    />

    <div class="auth__form">
      <!-- El toggle vive acá y no dentro de la tarjeta: el acceso es la
           primera pantalla del sistema, así que es el primer lugar donde
           alguien que trabaja en oscuro necesita poder cambiarlo. -->
      <div class="auth__theme">
        <SwitchDarkMode />
      </div>

      <main class="auth__panel">
        <header
          class="auth__head"
          style="--rise: 0"
        >
          <p class="auth__eyebrow">Bienvenido de vuelta</p>
          <h1 class="auth__title">Iniciá sesión</h1>
          <p class="auth__subtitle">
            Ingresá tus credenciales para entrar al panel.
          </p>
        </header>

        <!-- <form> nativo y no QForm: la validación es de dos campos
             obligatorios y la resolvemos acá. QForm sumaría su propio ciclo de
             validación encima del nuestro sin aportar nada. -->
        <form
          class="auth__fields"
          novalidate
          style="--rise: 1"
          @submit.prevent="onSubmit"
        >
          <AppTextField
            v-model="username"
            size="lg"
            label="Usuario"
            icon="person_outline"
            placeholder="tu.usuario"
            autocomplete="username"
            autofocus
            :error="errors.username"
          />

          <AppTextField
            v-model="password"
            size="lg"
            type="password"
            label="Contraseña"
            icon="lock_outline"
            placeholder="••••••••"
            autocomplete="current-password"
            :error="errors.password"
          />

          <div class="auth__row">
            <q-checkbox
              v-model="remember"
              dense
              size="xs"
              color="primary"
              label="Mantener la sesión iniciada"
              class="auth__remember"
            />

            <!-- Es un <button> y no un <a>: no navega a ningún lado, despliega
                 una aclaración. Un <a href="#"> acá haría que el lector de
                 pantalla anuncie un enlace que no lleva a ninguna parte. -->
            <button
              type="button"
              class="auth__link"
              @click="hintOpen = !hintOpen"
            >
              ¿Olvidaste tu contraseña?
            </button>
          </div>

          <!-- Hasta que haya un flujo de recuperación, la respuesta honesta es
               decir a quién pedirla. -->
          <p
            v-if="hintOpen"
            class="auth__hint"
          >
            <q-icon
              name="info_outline"
              size="15px"
            />
            Todavía no hay recuperación automática. Pedile a tu administrador
            que restablezca tu contraseña.
          </p>

          <!-- La regla de los dos rojos: el error NUNCA va relleno de marca.
               Tinte + contorno + ícono + la palabra. -->
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
            <span>Ingresar</span>
            <q-icon
              name="arrow_forward"
              size="17px"
              class="auth__submitIcon"
            />
          </AppButton>
        </form>

        <footer
          class="auth__foot"
          style="--rise: 2"
        >
          ¿Problemas para entrar? Escribile a tu administrador.
        </footer>
      </main>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/AppButton.vue'
import AppTextField from '@/components/AppTextField.vue'
import AuthShowcase from '@/components/AuthShowcase.vue'

// Se importa en vez de referenciar /public: así pasa por el build, se le pone
// hash en el nombre y el navegador puede cachearla para siempre sin que una
// foto nueva quede escondida detrás de la vieja.
import loginHero from '@/assets/login-hero.webp'
import SwitchDarkMode from '@/components/SwitchDarkMode.vue'
import { useUserStore, AuthError } from '@/stores/user-store'

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
  errors.username = username.value.trim() ? '' : 'Ingresá tu usuario.'
  errors.password = password.value ? '' : 'Ingresá tu contraseña.'
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
      : 'No pudimos conectarnos. Revisá tu conexión e intentá de nuevo.'

    password.value = ''
  } finally {
    pending.value = false
  }
}
</script>

<style lang="scss" scoped>
// ── Composición ───────────────────────────────────────────────────────────
// Dos columnas: lienzo de marca y formulario. La del formulario lleva un
// minmax con mínimo en px porque con dos fracciones, en 1024px el formulario
// se comprimía por debajo de su ancho cómodo y los campos quedaban angostos
// mientras el panel seguía enorme.
.auth {
  display: grid;
  grid-template-columns: minmax(0, 1.65fr) minmax(430px, 1fr);

  // dvh y no vh: en mobile el vh cuenta la barra del navegador como si no
  // existiera, así que el pie quedaba cortado.
  min-height: 100dvh;
  background: var(--app-surface);
}


.auth__showcase {
  min-width: 0;
}

.auth__form {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px;
}

.auth__theme {
  position: absolute;
  top: 24px;
  right: 28px;
}

.auth__panel {
  width: 100%;
  max-width: 384px;
}


// ── Encabezado ──
.auth__head {
  margin-bottom: 28px;
}

.auth__eyebrow {
  margin: 0 0 10px;
  font-family: $font-mono;
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: 1.8px;
  text-transform: uppercase;

  // El token, no $primary crudo: en oscuro el rojo de marca sobre superficie
  // oscura da 2.56:1 y este texto es chico, que es el peor caso.
  color: var(--app-brand-soft-ink);
}

.auth__title {
  margin: 0 0 8px;
  font-size: 30px;
  font-weight: 800;
  letter-spacing: -1px;

  // Sin esto hereda el line-height default de Quasar para <h1> (6rem): el
  // título infla su caja y empuja todo el formulario hacia abajo.
  line-height: 1.1;
  color: var(--app-ink);
}

.auth__subtitle {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.5;
  color: var(--app-ink-2);
}

// ── Formulario ──
.auth__fields {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.auth__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: -2px;
}

.auth__remember {
  font-size: 12.5px;
  color: var(--app-ink-2);
}

.auth__link {
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  font-size: 12px;
  font-weight: 600;
  color: var(--app-brand-soft-ink);
  cursor: pointer;

  &:hover {
    text-decoration: underline;
  }

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: 2px;
    border-radius: 3px;
  }
}

.auth__hint {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--app-page);
  border: 1px solid var(--app-border-subtle);
  font-size: 11.5px;
  line-height: 1.45;
  color: var(--app-ink-2);
}

.auth__error {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  margin: 0;
  padding: 11px 13px;
  border-radius: 10px;
  background: var(--app-negative-soft);
  border: 1px solid var(--app-negative-border);
  color: var(--app-ink-negative);
  font-size: 12.5px;
  line-height: 1.4;
}

.auth__errorIcon {
  flex-shrink: 0;
  margin-top: 1px;
}

.auth__submit {
  width: 100%;
  height: 48px;
  margin-top: 4px;
  font-size: 14.5px;

  // El contenido es texto + flecha y el slot los deja pegados.
  :deep(.q-btn__content) {
    gap: 9px;
  }
}

.auth__submitIcon {
  transition: transform 0.2s cubic-bezier(0.22, 1, 0.36, 1);
}

.auth__submit:hover .auth__submitIcon {
  transform: translateX(3px);
}

.auth__foot {
  margin-top: 28px;
  padding-top: 20px;
  border-top: 1px solid var(--app-border-subtle);
  text-align: center;
  font-size: 11.5px;
  color: var(--app-ink-2);
}

// ── Movimiento ──
// Igual que en el panel: todo adentro de la consulta, para que quien pidió
// menos movimiento vea la pantalla entera y quieta, no más lenta.
@media (prefers-reduced-motion: no-preference) {
  .auth__head,
  .auth__fields,
  .auth__foot {
    animation: auth-rise 0.6s cubic-bezier(0.22, 1, 0.36, 1) backwards;
    animation-delay: calc(120ms + var(--rise, 0) * 90ms);
  }
}

@keyframes auth-rise {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
}

// ── Sin lugar para el panel ───────────────────────────────────────────────
// El corte es 1024 y no un breakpoint de Quasar porque lo decide el ancho
// que necesita el panel para que el titular no se parta en tres líneas, no
// la clase de dispositivo.
@media (max-width: 1023px) {
  .auth {
    grid-template-columns: minmax(0, 1fr);

    // Las filas van declaradas y no implícitas: con dos filas `auto`, el grid
    // reparte el espacio sobrante ENTRE LAS DOS. La franja de 3px se quedaba
    // con la mitad y bajaba el formulario ~125px, que en mobile se veía como
    // un hueco enorme arriba. `auto 1fr` le da todo el resto al formulario.
    grid-template-rows: auto 1fr;
    background: var(--app-page);
  }

  // El panel no se oculta: se reduce a una franja con la foto y la marca.
  // La altura va acá y no adentro del componente porque es una decisión de
  // esta composición —cuánto cede el formulario— y no del panel en sí.
  .auth__showcase {
    height: 240px;
  }

  .auth__form {
    align-items: flex-start;
    padding: 32px 24px 40px;
  }

  // Sale del flujo del formulario y se va sobre la franja: ahí tiene una
  // fotografía oscura detrás —contraste de sobra— y deja de disputarle el
  // renglón al encabezado, que en 390px es un renglón que no sobra.
  .auth__theme {
    position: fixed;
    top: 14px;
    right: 14px;
  }
}

@media (max-width: 599px) {
  .auth__form {
    padding: 44px 18px 32px;
  }

  .auth__theme {
    top: 16px;
    right: 16px;
  }

  .auth__title {
    font-size: 26px;
  }
}
</style>
