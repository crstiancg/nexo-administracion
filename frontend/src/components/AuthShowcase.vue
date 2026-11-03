<template>
  <!-- aria-hidden y no un <aside> semántico: acá no hay información que el
       formulario no dé. Para quien navega con lector de pantalla esto sería
       un muro de adorno entre la URL y el campo de usuario. -->
  <section
    :class="['showcase', { 'showcase--photo': photo }]"
    aria-hidden="true"
  >
    <!-- ══ CAPAS DE FONDO ══════════════════════════════════════════════
         Capas quietas. Ninguna se mueve: son manchas grandes y al animarlas
         el panel entero parece temblar detrás del texto. Lo único que se
         anima en esta pantalla es el contenido, y sólo al entrar.

         Sin foto el panel se sostiene solo con las auroras. Con foto, la
         imagen va DEBAJO de todo y las auroras pasan a ser el velo que la
         tiñe de marca: sin ese velo, cualquier fotografía cálida hace que
         el acceso parezca de otro sistema. -->
    <img
      v-if="photo"
      :src="photo"
      alt=""
      class="showcase__photo"
    >

    <div class="showcase__aurora showcase__aurora--brand" />
    <div class="showcase__aurora showcase__aurora--deep" />
    <div class="showcase__aurora showcase__aurora--light" />
    <div class="showcase__grid" />

    <!-- El grano va como <svg> en el DOM y NO como background-image con un
         data:image/svg+xml. El index.html de la app declara una CSP con
         `default-src 'self'`, que bloquea las URLs data: — el grano
         simplemente no se dibujaba y no había forma de notarlo salvo mirando
         la consola. Un SVG inline no pasa por img-src. -->
    <svg
      class="showcase__grain"
      role="presentation"
    >
      <filter :id="grainId">
        <feTurbulence
          type="fractalNoise"
          base-frequency="0.85"
          num-octaves="3"
        />
      </filter>
      <rect
        width="100%"
        height="100%"
        :filter="`url(#${grainId})`"
      />
    </svg>

    <div class="showcase__scrim" />

    <div class="showcase__content">
      <!-- ── Marca ── -->
      <div
        class="showcase__brand"
        style="--rise: 0"
      >
        <AppBrandMark
          :size="42"
          glow
        />
        <div>
          <div class="showcase__brandName">NEXO</div>
          <div class="showcase__brandKicker">Panel de operación</div>
        </div>
      </div>

      <!-- ── Discurso ── -->
      <div class="showcase__pitch">
        <p
          class="showcase__eyebrow"
          style="--rise: 1"
        >
          Sistema de gestión
        </p>

        <h2
          class="showcase__headline"
          style="--rise: 2"
        >
          Tu operación entera,<br>
          en una sola pantalla.
        </h2>

        <div
          class="showcase__rule"
          style="--rise: 3"
        />

        <p
          class="showcase__lede"
          style="--rise: 4"
        >
          Servicio técnico, ventas y caja al día. Sin
          planillas sueltas, sin pedirle el número a nadie.
        </p>
      </div>

      <!-- ── Pie ── -->
      <div
        class="showcase__foot"
        style="--rise: 5"
      >
        <span>© {{ year }} NEXO TECHNOLOGY</span>
        <span class="showcase__footSep" />
        <span class="showcase__footItem">
          <q-icon
            name="lock"
            size="13px"
          />
          Conexión cifrada
        </span>
      </div>
    </div>
  </section>
</template>

<script setup>
import { useId } from 'vue'
import AppBrandMark from './AppBrandMark.vue'

defineProps({
  // URL de la fotografía de fondo. Vacío = el panel se dibuja solo con CSS,
  // que es como funciona hoy: el diseño NO depende de que exista la imagen.
  photo: {
    type: String,
    default: ''
  }
})

const year = new Date().getFullYear()

// El id del <filter> es GLOBAL al documento, no del componente: dos paneles
// con el mismo id y el segundo termina apuntando al filtro del primero.
// useId() lo hace único sin tener que prometer que esto se use una sola vez.
const grainId = useId()
</script>

<style lang="scss" scoped>
// ── Tinta del panel ──────────────────────────────────────────────────────
// El panel es SIEMPRE oscuro, en tema claro y en oscuro, y por eso no usa
// var(--app-*). No es un descuido: es un lienzo de marca, no una superficie
// del producto. En claro tiene que seguir siendo el bloque oscuro que hace
// de contrapeso al formulario; si siguiera el tema, la pantalla de acceso
// perdería su composición justo en el tema por defecto.
//
// Los tres tonos salen de $accent, que ya es el gris azulado del sistema.
// No entra ningún matiz nuevo: es la misma tinta más oscura.
$panel-ink: mix($accent, #000000, 46%);
$panel-ink-deep: mix($accent, #000000, 26%);

.showcase {
  position: relative;
  overflow: hidden;
  display: flex;
  background: linear-gradient(160deg, $panel-ink 0%, $panel-ink-deep 100%);
  color: #FFFFFF;
  isolation: isolate;
}

// ── Fotografía ──
// La foto es de clave baja y ya viene con su propia luz cálida, así que se
// toca poco: bajarla más la convierte en una mancha marrón. Lo único fuerte
// es el scrim de abajo, que es lo que hace legible el texto.
//
// object-position centrado en alto y a la derecha: el encuadre es 4:5 y el
// panel es más ancho que eso, así que `cover` recorta ARRIBA Y ABAJO, no a
// los lados. 46% sube el recorte lo justo para no comerse la repisa de ropa
// de niño, que es la mitad del mensaje de la imagen.
.showcase__photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: 62% 46%;
  filter: saturate(0.92) contrast(1.03) brightness(0.86);
}

.showcase--photo {
  // El degradado de base pelea con la foto: la tapa con un marrón plano.
  background: #0A0C0F;

  // Tres scrims, de arriba hacia abajo en la pila: la cama del titular, el
  // vertical que asienta marca y pie, y el diagonal que abre una cuña de
  // sombra sobre la mitad izquierda. Sin ellos, el blanco se apoya sobre la
  // pared iluminada de la foto y pierde contraste.
  .showcase__scrim {
    background:
      // Cama de sombra del titular, anclada a la esquina inferior izquierda.
      // Va radial y no como franja horizontal porque el texto ahora ocupa el
      // pie del panel y una franja plana apagaría también la repisa de ropa
      // de niño, que está abajo a la derecha y es media imagen.
      radial-gradient(125% 82% at 0% 100%, rgba(#000000, 0.90), rgba(#000000, 0.42) 44%, transparent 70%),
      linear-gradient(to bottom, rgba(#000000, 0.42), transparent 22%, transparent 52%, rgba(#000000, 0.72)),
      linear-gradient(102deg, rgba(#000000, 0.88) 4%, rgba(#000000, 0.66) 30%, rgba(#000000, 0.20) 58%, transparent 74%);
  }

  // Las auroras pasan a segundo plano: la foto ya trae rojos y luz propia, y
  // al volumen anterior se sumaban hasta dar un halo anaranjado sucio.
  .showcase__aurora--brand {
    opacity: 0.40;
  }

  .showcase__aurora--deep {
    opacity: 0.28;
  }

  // Fuera: su único trabajo era fingir una fuente de luz, y ahora hay una de
  // verdad entrando por la derecha del encuadre.
  .showcase__aurora--light {
    display: none;
  }

  // La retícula sobre una fotografía se lee como suciedad, no como estructura.
  .showcase__grid {
    opacity: 0.22;
  }

  // Menos grano: la fotografía ya trae el suyo, y sumarle el sintético encima
  // ensucia las lanas en vez de unificar.
  .showcase__grain {
    opacity: 0.10;
  }
}

// ── Auroras ──
// Tres manchas: dos de marca (rojo y su hover, que dan profundidad sin sumar
// un color) y una blanca que hace de fuente de luz. Sin la blanca el rojo
// parece pintado encima en vez de iluminar el panel.
.showcase__aurora {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
  filter: blur(10px);
}

.showcase__aurora--brand {
  top: -18%;
  left: -14%;
  width: 62%;
  aspect-ratio: 1;
  background: radial-gradient(circle, rgba($primary, 0.40), transparent 62%);
}

.showcase__aurora--deep {
  right: -20%;
  bottom: -22%;
  width: 70%;
  aspect-ratio: 1;
  background: radial-gradient(circle, rgba($primary-hover, 0.34), transparent 64%);
}

.showcase__aurora--light {
  top: -26%;
  right: 2%;
  width: 46%;
  aspect-ratio: 1;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.10), transparent 66%);
}

// ── Retícula ──
// Se desvanece hacia los bordes con una máscara: cortada en seco parecía un
// mantel y peleaba con el borde del panel.
.showcase__grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.045) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.045) 1px, transparent 1px);
  background-size: 64px 64px;
  mask-image: radial-gradient(115% 90% at 20% 15%, #000 0%, transparent 72%);
  -webkit-mask-image: radial-gradient(115% 90% at 20% 15%, #000 0%, transparent 72%);
  pointer-events: none;
}

// ── Grano ──
// Rompe el banding de los degradados. Sin esto, en pantallas de 8 bits las
// auroras se ven en escalones concéntricos.
//
// Es un <svg> del DOM, no un background: la CSP de index.html bloquea data:.
.showcase__grain {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0.15;
  mix-blend-mode: overlay;
  pointer-events: none;
}

// Cama de sombra abajo: el pie y el texto se apoyan sobre la aurora roja y
// sin esto el blanco al 40% se les pierde encima.
.showcase__scrim {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: linear-gradient(to bottom, rgba(#000000, 0.28), transparent 26%, transparent 56%, rgba(#000000, 0.52));
}

.showcase__content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;

  // flex-start + margin-top:auto en el discurso, y NO space-between. Con tres
  // bloques, space-between reparte el sobrante en DOS huecos iguales y el
  // panel se ve vacío arriba y abajo del texto. Así el sobrante se junta en
  // un solo hueco, arriba, que es donde la fotografía tiene algo que mostrar:
  // el texto se apoya sobre el pie y queda sobre la zona más oscura del
  // encuadre, que además es la que mejor lo sostiene.
  justify-content: flex-start;
  gap: 30px;
  width: 100%;
  padding: 52px;
}

.showcase__pitch {
  margin-top: auto;
}

// ── Marca ──
.showcase__brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.showcase__brandName {
  font-size: 16px;
  font-weight: 700;
  letter-spacing: -0.2px;
  line-height: 1.2;
}

.showcase__brandKicker {
  font-family: $font-mono;
  font-size: 10px;
  letter-spacing: 1.4px;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.52);
}

// ── Discurso ──
.showcase__eyebrow {
  margin: 0 0 14px;
  font-family: $font-mono;
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: 2px;
  text-transform: uppercase;

  // Rojo crudo sobre este fondo da 4.9:1 — pasa AA para texto pequeño. Es el
  // único texto de marca del panel; el resto es blanco a distintas alphas.
  color: mix($primary, #FFFFFF, 74%);
}

.showcase__headline {
  margin: 0;

  // clamp y no un tamaño fijo: entre 1024px y 1920px este panel cambia de
  // ancho casi el doble, y un titular fijo se ve enorme abajo o perdido arriba.
  font-size: clamp(2.35rem, 3.1vw, 3.5rem);
  font-weight: 800;
  letter-spacing: -1.6px;
  line-height: 1.02;
}

.showcase__rule {
  width: 52px;
  height: 3px;
  margin: 20px 0 18px;
  border-radius: 999px;
  background: linear-gradient(90deg, $primary, rgba($primary, 0));
}

.showcase__lede {
  max-width: 40ch;
  margin: 0;
  font-size: 14.5px;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.62);
}

// ── Pie ──
.showcase__foot {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 11.5px;
  color: rgba(255, 255, 255, 0.42);
}

.showcase__footSep {
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: currentColor;
}

.showcase__footItem {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

// ── Movimiento ────────────────────────────────────────────────────────────
// Todo el movimiento vive dentro de esta consulta. No es un extra opcional:
// para quien marcó "reducir movimiento" el panel tiene que aparecer entero y
// quieto, no aparecer más lento.
@media (prefers-reduced-motion: no-preference) {
  .showcase__content > *,
  .showcase__pitch > * {
    animation: showcase-rise 0.66s cubic-bezier(0.22, 1, 0.36, 1) backwards;

    // El escalonado se declara en el template con --rise y no con :nth-child
    // porque los hijos están repartidos en dos contenedores distintos.
    animation-delay: calc(var(--rise, 0) * 90ms);
  }
}

@keyframes showcase-rise {
  from {
    opacity: 0;
    transform: translateY(14px);
  }
}

// Por debajo de 1280 el panel se angosta y el titular empieza a partirse en
// tres líneas; achicamos el aire antes de que eso pase.
@media (max-width: 1279px) {
  .showcase__content {
    padding: 40px;
  }

  .showcase__headline {
    letter-spacing: -1px;
  }
}

// ── Banda de mobile ───────────────────────────────────────────────────────
// Sin lugar para la columna, el panel no desaparece: se reduce a una franja
// con la fotografía y la marca. Antes se ocultaba entero y en teléfono —que
// es donde más gente entra— la pantalla quedaba sin una sola señal de marca.
//
// Se va el discurso y se va la tarjeta de datos: en 190px de alto no entran,
// y apilarlos encima del formulario obligaría a bajar para ver el primer
// campo. El formulario manda; la franja es el marco.
@media (max-width: 1023px) {
  .showcase__content {
    padding: 20px 22px;
  }

  .showcase__pitch,
  .showcase__foot {
    display: none;
  }

  // La retícula a esta escala es ruido: sus celdas de 64px, en una franja de
  // 190, se leen como un defecto de la imagen.
  .showcase__grid {
    display: none;
  }
}
</style>
