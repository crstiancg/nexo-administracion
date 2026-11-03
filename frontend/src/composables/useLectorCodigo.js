import { onBeforeUnmount, onMounted } from 'vue'

/**
 * Detecta un lector de código de barras en TODA la página, tenga el foco
 * donde tenga. Un lector "tipea" como un teclado pero en ráfaga (menos de
 * ~50 ms entre teclas) y termina con Enter; una persona no escribe así.
 *
 * - Si la ráfaga cayó dentro de un campo (el buscador, por ejemplo), se
 *   devuelve el campo a como estaba: el código no queda escrito ahí.
 * - El Enter del lector no llega a los formularios (no dispara un submit).
 *
 * @param {(codigo: string) => void} onCodigo
 * @param {{ habilitado?: () => boolean, maxIntervalo?: number, minLargo?: number }} opciones
 */
export function useLectorCodigo (onCodigo, { habilitado = () => true, maxIntervalo = 50, minLargo = 4 } = {}) {
  let buffer = ''
  let ultimo = 0
  let campo = null
  let valorPrevio = ''

  function reiniciar () {
    buffer = ''
    campo = null
  }

  function esCampo (el) {
    return el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement
  }

  function alPresionar (evento) {
    if (!habilitado()) {
      reiniciar()
      return
    }

    const ahora = performance.now()
    const enRafaga = ahora - ultimo <= maxIntervalo
    ultimo = ahora

    if (evento.key === 'Enter') {
      if (enRafaga && buffer.length >= minLargo) {
        evento.preventDefault()
        evento.stopPropagation()
        // Deshacer lo que la ráfaga escribió en el campo con foco.
        if (campo && esCampo(campo) && campo.value !== valorPrevio) {
          campo.value = valorPrevio
          campo.dispatchEvent(new Event('input', { bubbles: true }))
        }
        onCodigo(buffer)
      }
      reiniciar()
      return
    }

    // Sólo caracteres imprimibles (Shift sí: los códigos llevan mayúsculas).
    if (evento.key.length !== 1 || evento.ctrlKey || evento.metaKey || evento.altKey) {
      reiniciar()
      return
    }

    // Primera tecla de una posible ráfaga: se recuerda el campo y su valor
    // ANTES de que la tecla lo modifique (estamos en fase de captura).
    if (!enRafaga || !buffer) {
      buffer = ''
      campo = document.activeElement
      valorPrevio = esCampo(campo) ? campo.value : ''
    }
    buffer += evento.key
  }

  // Captura: corre antes que los manejadores de los campos y del resto.
  onMounted(() => window.addEventListener('keydown', alPresionar, true))
  onBeforeUnmount(() => window.removeEventListener('keydown', alPresionar, true))
}
