import Compressor from 'compressorjs'

/**
 * Optimiza una foto ANTES de subirla. Es la única compresión que recibe el
 * original: el backend sólo genera la miniatura (otra pasada de JPEG sumaría
 * pérdida generacional).
 *
 * - Lado máximo 1920 px: sobra para ver un producto en pantalla completa, y
 *   una foto de celular (4000 px, 5 MB) queda en ~300-600 KB.
 * - Calidad 0.85: a la vista es indistinguible del original.
 * - Corrige la orientación EXIF y descarta los metadatos, incluida la
 *   ubicación GPS que traen las fotos de celular.
 * - Un PNG grande pasa a WebP y no a JPEG: conserva la transparencia de las
 *   fotos recortadas sin fondo.
 * - Si el resultado pesa más que el original (una foto ya optimizada), se
 *   queda el original (`strict`, que viene activado).
 */
export const LADO_MAXIMO = 1920
export const CALIDAD = 0.85

const PNG_GRANDE = 1024 * 1024

/**
 * El formato REAL, leyendo la firma del archivo (magic bytes). `file.type`
 * sale de la extensión: una imagen bajada de internet como "foto.jpg" suele
 * ser WebP o AVIF por dentro, y ahí el navegador igual dice image/jpeg.
 *
 * @param {Blob} archivo
 * @returns {Promise<string|null>} 'image/jpeg' | 'image/png' | 'image/webp' | 'image/avif' | 'image/heic' | 'image/gif' | null
 */
export async function detectarTipoReal (archivo) {
  const b = new Uint8Array(await archivo.slice(0, 16).arrayBuffer())
  const texto = (desde, hasta) => String.fromCharCode(...b.slice(desde, hasta))

  if (b[0] === 0xFF && b[1] === 0xD8 && b[2] === 0xFF) return 'image/jpeg'
  if (b[0] === 0x89 && texto(1, 4) === 'PNG') return 'image/png'
  if (texto(0, 4) === 'RIFF' && texto(8, 12) === 'WEBP') return 'image/webp'
  if (texto(0, 3) === 'GIF') return 'image/gif'
  // ISO-BMFF: "ftyp" + marca. AVIF (web) y HEIC (fotos de iPhone).
  if (texto(4, 8) === 'ftyp') {
    const marca = texto(8, 12)
    if (marca.startsWith('avi')) return 'image/avif'
    if (['heic', 'heix', 'mif1', 'msf1', 'hevc'].includes(marca)) return 'image/heic'
  }
  return null
}

/**
 * Si el navegador puede abrir la imagen. Una firma válida no alcanza: un
 * archivo cortado a mitad de descarga, o un JPEG con una codificación que los
 * navegadores no soportan (aritmética, 12 bits, algunos CMYK de imprenta),
 * empieza igual que uno sano y después no se ve.
 *
 * @param {Blob} archivo
 * @returns {Promise<string|null>} null si se puede abrir; si no, el motivo técnico
 */
export async function problemaAlDecodificar (archivo) {
  try {
    const imagen = await createImageBitmap(archivo)
    imagen.close()
    return null
  } catch (error) {
    return error?.message || String(error)
  }
}

/**
 * @param {File} archivo
 * @returns {Promise<File>}
 */
/**
 * - PNG grande → WebP (conserva la transparencia).
 * - AVIF → WebP: el canvas del navegador no sabe GUARDAR AVIF (lo pasaría a
 *   PNG, que pesa diez veces más). Con `strict`, si el WebP pesa más que el
 *   AVIF original (lo normal: AVIF comprime mejor), se queda el AVIF.
 */
function formatoSalida (archivo) {
  if (archivo.type === 'image/avif') return 'image/webp'
  if (archivo.type === 'image/png' && archivo.size > PNG_GRANDE) return 'image/webp'
  return 'auto'
}

export function comprimirImagen (archivo) {
  return new Promise((resolve, reject) => {
    // eslint-disable-next-line no-new
    new Compressor(archivo, {
      maxWidth: LADO_MAXIMO,
      maxHeight: LADO_MAXIMO,
      quality: CALIDAD,
      mimeType: formatoSalida(archivo),
      // Sin esto, un PNG > 5 MB se convierte a JPEG y pierde la transparencia.
      convertSize: Infinity,
      success (resultado) {
        // Compressor devuelve un Blob cuando cambia el tipo: se rearma el File
        // con el nombre y la extensión que correspondan.
        if (resultado instanceof File) return resolve(resultado)

        const extension = resultado.type.split('/')[1]?.replace('jpeg', 'jpg')
        const nombre = archivo.name.replace(/\.[^.]+$/, '') + (extension ? `.${extension}` : '')
        resolve(new File([resultado], nombre, { type: resultado.type, lastModified: Date.now() }))
      },
      error: reject
    })
  })
}
