<template>
  <!-- Los escuchas van en el contenedor y no en el <q-input>: QInput filtra
       qué eventos reenvía al input nativo (pisa blur y focus con los suyos),
       así que lo que se le cuelgue por fuera puede no llegar nunca. Acá los
       agarramos burbujeando, que es DOM puro y no depende de Quasar.
       focusout y no blur: blur no burbujea. -->
  <div
    class="app-field"
    @keyup="checkCapsLock"
    @keydown="checkCapsLock"
    @focusout="capsLock = false"
  >
    <!-- La etiqueta va FUERA del campo y no como `label` de Quasar: la
         flotante de Quasar se encoge dentro del control al escribir, y en un
         formulario de acceso el nombre del campo tiene que seguir legible
         mientras se tipea (sobre todo con la contraseña oculta). -->
    <div class="app-field__top">
      <label
        :id="labelId"
        class="app-field__label"
        :for="fieldId"
      >{{ label }}</label>

      <slot name="aside" />
    </div>

    <q-input
      v-bind="$attrs"
      v-model="model"
      :for="fieldId"
      :aria-labelledby="labelId"
      dense
      outlined
      hide-bottom-space
      no-error-icon
      :type="inputType"
      :aria-describedby="hint ? hintId : undefined"
      :error="Boolean(error)"
      :error-message="error"
      :class="['app-field__control', `app-field__control--${size}`]"
    >
      <template
        v-if="icon"
        #prepend
      >
        <q-icon
          :name="icon"
          class="app-field__icon"
        />
      </template>

      <!-- Mostrar/ocultar. tabindex=-1 a propósito: el tabulador tiene que ir
           del usuario a la contraseña y de ahí al botón de ingresar, sin un
           escalón intermedio que es ayuda visual, no un paso del formulario.
           Sigue siendo alcanzable por clic y tiene nombre accesible. -->
      <template
        v-if="revealable"
        #append
      >
        <q-btn
          flat
          dense
          round
          size="sm"
          tabindex="-1"
          :icon="revealed ? 'visibility_off' : 'visibility'"
          :aria-label="revealed ? 'Ocultar contraseña' : 'Mostrar contraseña'"
          :aria-pressed="String(revealed)"
          class="app-field__reveal"
          @click="revealed = !revealed"
        />
      </template>
    </q-input>

    <!-- Leyenda: qué dato va en el campo. Se oculta mientras hay error
         (el error ya dice qué corregir y dos textos juntos se pisan). -->
    <p
      v-if="hint && !error"
      :id="hintId"
      class="app-field__hint"
    >
      {{ hint }}
    </p>

    <!-- Bloq Mayús. Es la causa número uno de "mi contraseña no anda" y el
         navegador no la reporta en ningún lado cuando el campo está oculto.
         Va en aviso (amarillo) y no en error (rojo): todavía no falló nada.
         role="status" y no "alert": interrumpir el dictado por esto es peor
         que el problema. -->
    <p
      v-if="capsLock && !error"
      class="app-field__caps"
      role="status"
    >
      <q-icon
        name="keyboard_capslock"
        size="15px"
      />
      Bloq Mayús está activado.
    </p>
  </div>
</template>

<script>
export const SIZES = ['md', 'lg']
</script>

<script setup>
import { computed, ref, useId } from 'vue'

// Sin esto, un `autocomplete` o un `autofocus` puesto desde afuera caería en
// el <div> que envuelve todo y el input nativo no se enteraría: el gestor de
// contraseñas del navegador no reconocería el campo y nadie vería el error.
defineOptions({ inheritAttrs: false })

const props = defineProps({
  label: {
    type: String,
    required: true
  },

  // 'password' es el único que habilita el ojito y el aviso de Bloq Mayús.
  // El resto viaja tal cual al input nativo (text, email, tel…).
  type: {
    type: String,
    default: 'text'
  },

  icon: {
    type: String,
    default: ''
  },

  // Mensaje de error, no booleano: un campo en rojo sin decir qué pasa
  // obliga a adivinar. Vacío = sin error.
  error: {
    type: String,
    default: ''
  },

  // Leyenda corta debajo del campo: qué se registra ahí.
  hint: {
    type: String,
    default: ''
  },

  // 'lg' es para pantallas donde el formulario ES la pantalla (el acceso).
  // En una tabla o un diálogo el campo va en 'md', que es el alto del resto
  // del sistema.
  size: {
    type: String,
    default: 'md',
    validator: (value) => SIZES.includes(value)
  }
})

const model = defineModel({ type: String, default: '' })

// useId() da un id estable y único por instancia. Hace falta uno real porque
// el <label for> de arriba tiene que apuntar al input nativo que dibuja
// Quasar adentro; sin eso el campo se queda sin nombre accesible.
const fieldId = useId()

// aria-labelledby además del <label for>, y no por las dudas: QField envuelve
// el control en OTRO <label>, y el algoritmo de nombre accesible concatena
// todas las etiquetas que apuntan al campo. Con el ojito adentro de ese
// wrapper, el lector de pantalla anunciaba "Contraseña Mostrar contraseña".
// aria-labelledby gana sobre los <label> y deja el nombre limpio.
const labelId = `${fieldId}-label`
const hintId = `${fieldId}-hint`

const revealed = ref(false)
const revealable = computed(() => props.type === 'password')
const inputType = computed(() => (revealable.value && revealed.value ? 'text' : props.type))

const capsLock = ref(false)

function checkCapsLock (event) {
  if (!revealable.value) return

  // getModifierState es la única forma de saberlo: no hay evento propio de
  // Bloq Mayús, se consulta sobre un evento de teclado cualquiera. Algunos
  // eventos sintéticos no lo traen, de ahí el guard.
  if (typeof event?.getModifierState !== 'function') return

  capsLock.value = event.getModifierState('CapsLock')
}
</script>

<style lang="scss" scoped>
.app-field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.app-field__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.app-field__label {
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: -0.1px;
  color: var(--app-ink);
}

// Mismo radio y borde que el resto de los campos del sistema (buscador,
// selects de los forms): el outlined default de Quasar viene en 4px y con un borde
// negro fijo que en oscuro no se ve.
.app-field__control {
  :deep(.q-field__control) {
    border-radius: 10px;
    background: var(--app-surface);

    // La transición es del borde y la sombra, no de `all`: con all, Quasar
    // anima también el color del texto al entrar en error y el mensaje
    // aparece desteñido.
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }

  :deep(.q-field__control):before {
    border-color: var(--app-border-control);
  }

  :deep(.q-field__control):hover:before {
    border-color: var(--app-border-control-hover);
  }

  // Anillo de foco de marca. Quasar dibuja el suyo engordando el borde con
  // :after, que a 2px corre el contenido 1px; el box-shadow no ocupa espacio.
  //
  // La clase q-field--focused cae en ESTE mismo elemento (es la raíz del
  // QInput), así que va con & y el :deep sólo para bajar al control.
  &.q-field--focused :deep(.q-field__control) {
    box-shadow: 0 0 0 3px rgba($primary, 0.12);
  }
}

.app-field__control--lg {
  :deep(.q-field__control),
  :deep(.q-field__marginal) {
    height: 48px;
  }

  :deep(.q-field__native) {
    font-size: 14px;
  }
}

.app-field__icon,
.app-field__reveal {
  color: var(--app-ink-2);
}

.app-field__reveal:hover {
  color: var(--app-ink);
}

// $negative crudo —el que usa Quasar para el estado de error— da 2.29:1
// sobre superficie oscura. El token se aclara solo cuando cambia el tema.
.app-field__control.q-field--error {
  :deep(.q-field__control):before,
  :deep(.q-field__control):hover:before {
    border-color: var(--app-ink-negative);
  }

  :deep(.q-field__messages) {
    color: var(--app-ink-negative);
  }
}

.app-field__hint {
  margin: 0;
  font-size: 11.5px;
  line-height: 1.45;
  color: var(--app-ink-2);
}

// Aviso, no error: el amarillo sale del mismo mapa de estados que los chips,
// así que no puede caerse de contraste ni divergir del resto del sistema.
.app-field__caps {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 11.5px;
  color: var(--app-ink-warning);
}

// Mismo alto táctil que AppButton en mobile: si el botón crece y el campo no,
// el formulario queda desparejo justo donde más cuesta acertarle.
//
// Sólo alcanza a `md`: `lg` ya mide 48px y esta regla lo ACHICARÍA a 44
// justo en la pantalla donde más importa acertarle.
@media (max-width: 599px) {
  .app-field__control--md :deep(.q-field__control) {
    height: 44px;
  }
}
</style>
