<template>
  <div class="app-fotos">
    <ul
      class="app-fotos__grid"
      :aria-label="label"
    >
      <li
        v-for="(foto, i) in model"
        :key="foto.url"
        class="app-fotos__item"
      >
        <!-- Miniatura en la grilla; el original recién al ampliar. -->
        <button
          type="button"
          class="app-fotos__ver"
          :aria-label="`Ampliar ${foto.nombre || `foto ${i + 1}`}`"
          @click="ampliada = foto"
        >
          <img
            v-if="!rotas.has(foto.url)"
            :src="foto.miniatura_url ?? foto.url"
            :alt="foto.nombre || `Foto ${i + 1}`"
            width="84"
            height="84"
            class="app-fotos__img"
            loading="lazy"
            decoding="async"
            @error="marcarRota(foto)"
          >
          <!-- Una URL que no carga (servidor, APP_URL mal configurada…) no
               deja un recuadro roto con el nombre del archivo encima. -->
          <span
            v-else
            class="app-fotos__rota"
            :title="`No se pudo cargar ${foto.nombre}`"
          >
            <q-icon
              name="broken_image"
              size="22px"
            />
          </span>
        </button>

        <span
          v-if="i === 0"
          class="app-fotos__portada"
        >Portada</span>

        <div class="app-fotos__acciones">
          <q-btn
            v-if="i > 0"
            flat
            dense
            round
            size="xs"
            icon="star_outline"
            :aria-label="`Usar foto ${i + 1} como portada`"
            @click="hacerPortada(i)"
          >
            <q-tooltip>Usar como portada</q-tooltip>
          </q-btn>
          <q-btn
            flat
            dense
            round
            size="xs"
            icon="close"
            :aria-label="`Quitar foto ${i + 1}`"
            @click="quitar(i)"
          />
        </div>
      </li>

      <li
        v-if="optimizando"
        class="app-fotos__item app-fotos__item--procesando"
        role="status"
      >
        <q-spinner size="20px" />
        <span>Optimizando…</span>
      </li>

      <li
        v-else-if="model.length < max"
        class="app-fotos__item app-fotos__item--agregar"
      >
        <label class="app-fotos__agregar">
          <q-icon
            name="add_a_photo"
            size="20px"
          />
          <span>{{ model.length ? 'Agregar' : 'Agregar fotos' }}</span>
          <input
            type="file"
            :accept="TIPOS.join(',')"
            multiple
            class="app-fotos__input"
            :aria-label="`Agregar fotos: ${label}`"
            @change="agregar"
          >
        </label>
      </li>
    </ul>

    <p
      v-for="mensaje in [...rechazos, ...errores]"
      :key="mensaje"
      class="app-fotos__error"
      role="alert"
    >
      {{ mensaje }}
    </p>

    <q-dialog v-model="hayAmpliada">
      <figure
        v-if="ampliada"
        class="app-fotos__visor"
      >
        <img
          :src="ampliada.url"
          :alt="ampliada.nombre"
          :width="ampliada.ancho ?? undefined"
          :height="ampliada.alto ?? undefined"
          class="app-fotos__visorImg"
        >
        <figcaption class="app-fotos__visorPie">
          {{ ampliada.nombre }}
          <template v-if="ampliada.ancho">
            · {{ ampliada.ancho }}×{{ ampliada.alto }}
          </template>
        </figcaption>
      </figure>
    </q-dialog>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { comprimirImagen, detectarTipoReal, problemaAlDecodificar } from '@/utils/imagenes'

/**
 * Galería editable para los archivos polimórficos del backend
 * (App\Services\ArchivosService, sale por ArchivoResource). Cada ítem es:
 *   - una foto ya guardada: { id, url, miniatura_url, nombre, ancho, alto }
 *   - una nueva:            { archivo: File, url: 'blob:…', nombre }
 * El orden del array es el orden final; la primera es la portada.
 *
 * Las fotos nuevas se comprimen acá antes de agregarse (utils/imagenes) y
 * RECIÉN DESPUÉS se chequea el peso: una foto de celular de 10 MB entra sin
 * problema porque sale de acá con ~500 KB. Tipo y peso los valida también el
 * backend; acá es para avisar al elegir y no recién al guardar.
 *
 * Las URLs blob: NO se revocan acá: la misma foto puede estar copiada en
 * otra galería, y ésta puede desmontarse con la foto todavía en uso. Las
 * revoca el dueño del estado (el form) al cerrarse.
 */
// AVIF: pesa poco y conserva la calidad (navegadores y backend lo leen).
const TIPOS = ['image/jpeg', 'image/png', 'image/webp', 'image/avif']
const MAX_BYTES = 4 * 1024 * 1024

const props = defineProps({
  label: {
    type: String,
    default: 'Fotos'
  },
  max: {
    type: Number,
    default: 6
  },
  // Mensajes del backend (form.errors) para esta galería.
  errores: {
    type: Array,
    default: () => []
  }
})

const model = defineModel({ type: Array, default: () => [] })

const rechazos = ref([])
const optimizando = ref(false)

const rotas = ref(new Set())
function marcarRota (foto) {
  console.warn(`No se pudo cargar la imagen ${foto.nombre}:`, foto.miniatura_url ?? foto.url)
  rotas.value = new Set([...rotas.value, foto.url])
}

const ampliada = ref(null)
const hayAmpliada = computed({
  get: () => ampliada.value !== null,
  set: (abierta) => { if (!abierta) ampliada.value = null }
})

async function agregar (event) {
  const elegidos = Array.from(event.target.files ?? [])
  event.target.value = ''
  rechazos.value = []

  if (!elegidos.length) return

  optimizando.value = true
  try {
    // El tipo por el CONTENIDO, no por la extensión: una "foto.jpg" bajada de
    // internet suele ser WebP o AVIF por dentro.
    const tipos = await Promise.all(elegidos.map(detectarTipoReal))

    // Y que el navegador la pueda abrir: si no, quedaría una miniatura rota.
    const problemas = await Promise.all(elegidos.map((archivo, i) =>
      TIPOS.includes(tipos[i]) ? problemaAlDecodificar(archivo) : null))

    const lugares = props.max - model.value.length
    const validos = []

    elegidos.forEach((archivo, i) => {
      const tipo = tipos[i]

      if (!TIPOS.includes(tipo)) {
        rechazos.value.push(`${archivo.name}: ${motivoTipo(tipo)}`)
      } else if (problemas[i]) {
        console.warn(`El navegador no puede abrir ${archivo.name} (${tipo}):`, problemas[i])
        rechazos.value.push(`${archivo.name}: está dañada o usa una codificación que el navegador no puede abrir. Abrila y guardala de nuevo como JPG (por ejemplo, con Paint o una captura).`)
      } else if (validos.length >= lugares) {
        rechazos.value.push(`Máximo ${props.max} fotos: ${archivo.name} quedó afuera.`)
      } else {
        validos.push(conTipoReal(archivo, tipo))
      }
    })

    // En paralelo: cada compresión corre en su propio canvas.
    const resultados = await Promise.allSettled(validos.map(comprimirImagen))

    const nuevos = []
    resultados.forEach((resultado, i) => {
      // Optimizar es una mejora, no un requisito: si no se pudo, va el
      // original (el backend lo valida igual).
      if (resultado.status === 'rejected') {
        console.warn(`No se pudo optimizar ${validos[i].name}; se sube el original.`, resultado.reason)
      }
      const archivo = resultado.status === 'fulfilled' ? resultado.value : validos[i]

      if (archivo.size > MAX_BYTES) {
        rechazos.value.push(`${validos[i].name}: pesa más de 4 MB${resultado.status === 'fulfilled' ? ' aun optimizada' : ''}.`)
      } else {
        nuevos.push({ archivo, url: URL.createObjectURL(archivo), nombre: archivo.name })
      }
    })

    if (nuevos.length) model.value = [...model.value, ...nuevos]
  } finally {
    optimizando.value = false
  }
}

function motivoTipo (tipo) {
  if (tipo === 'image/heic') return 'es HEIC (formato de iPhone). Exportala como JPG.'
  if (tipo === 'image/gif') return 'los GIF no se aceptan. Usá JPG, PNG, WEBP o AVIF.'
  return 'no es una imagen JPG, PNG, WEBP o AVIF válida.'
}

// Si la extensión mentía ("foto.jpg" que es WebP), se corrige el tipo y la
// extensión: así Compressor y el backend lo tratan como lo que realmente es.
function conTipoReal (archivo, tipo) {
  if (archivo.type === tipo) return archivo

  const extension = tipo.split('/')[1].replace('jpeg', 'jpg')
  const nombre = archivo.name.replace(/\.[^.]+$/, '') + `.${extension}`
  return new File([archivo], nombre, { type: tipo, lastModified: archivo.lastModified })
}

function quitar (i) {
  model.value = model.value.filter((_, j) => j !== i)
}

function hacerPortada (i) {
  const copia = [...model.value]
  const [foto] = copia.splice(i, 1)
  model.value = [foto, ...copia]
}
</script>

<style lang="scss" scoped>
.app-fotos__grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.app-fotos__item {
  position: relative;
  width: 84px;
  height: 84px;
  border: 1px solid var(--app-border-subtle);
  border-radius: 10px;
  overflow: hidden;
  background: var(--app-surface);
}

.app-fotos__ver {
  display: block;
  width: 100%;
  height: 100%;
  padding: 0;
  border: 0;
  background: none;
  cursor: zoom-in;

  &:focus-visible {
    outline: 2px solid $primary;
    outline-offset: -2px;
  }
}

.app-fotos__img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.app-fotos__rota {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: var(--app-ink-2);
}

.app-fotos__portada {
  position: absolute;
  left: 4px;
  bottom: 4px;
  padding: 1px 6px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 600;
  background: rgba(0, 0, 0, 0.6);
  color: #FFFFFF;
  pointer-events: none;
}

// Siempre visibles (no sólo en hover): en pantallas táctiles no hay hover.
.app-fotos__acciones {
  position: absolute;
  top: 2px;
  right: 2px;
  display: flex;
  gap: 2px;

  .q-btn {
    background: rgba(0, 0, 0, 0.55);
    color: #FFFFFF;
  }
}

.app-fotos__item--agregar {
  border-style: dashed;
  border-color: var(--app-border-control);
}

.app-fotos__item--procesando {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 11px;
  color: var(--app-ink-2);
}

.app-fotos__agregar {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  width: 100%;
  height: 100%;
  font-size: 11px;
  text-align: center;
  color: var(--app-ink-2);
  cursor: pointer;

  &:hover {
    color: var(--app-ink);
  }

  &:focus-within {
    outline: 2px solid $primary;
    outline-offset: -2px;
  }
}

// Oculto pero enfocable y operable por teclado a través del <label>.
.app-fotos__input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  overflow: hidden;
}

.app-fotos__error {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--q-negative);
}

.app-fotos__visor {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  max-width: 92vw;
}

// width/height reservan la proporción; esto lo ajusta a la pantalla.
.app-fotos__visorImg {
  display: block;
  width: auto;
  height: auto;
  max-width: 92vw;
  max-height: 82vh;
  border-radius: 10px;
  background: var(--app-surface);
}

.app-fotos__visorPie {
  font-size: 12px;
  text-align: center;
  color: #FFFFFF;
}
</style>
