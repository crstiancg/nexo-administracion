/**
 * Beeps cortos para el punto de venta: la cajera sabe si el escaneo entró
 * sin mirar la pantalla (un aviso emergente por producto sería ruido).
 *
 * Web Audio, sin archivos. El navegador sólo deja sonar después de una
 * interacción del usuario: el primer beep antes de cualquier clic o tecla
 * puede no oírse, y no pasa nada.
 */
let contexto = null

function tono (frecuencia, duracion, inicio = 0) {
  try {
    contexto ??= new (window.AudioContext || window.webkitAudioContext)()
    const t = contexto.currentTime + inicio
    const oscilador = contexto.createOscillator()
    const volumen = contexto.createGain()

    oscilador.type = 'square'
    oscilador.frequency.value = frecuencia
    // Volumen bajo y con rampa: sin "clic" al cortar.
    volumen.gain.setValueAtTime(0.06, t)
    volumen.gain.exponentialRampToValueAtTime(0.0001, t + duracion)

    oscilador.connect(volumen).connect(contexto.destination)
    oscilador.start(t)
    oscilador.stop(t + duracion)
  } catch {
    // Sin audio disponible: el aviso visual alcanza.
  }
}

// Agudo y corto: entró.
export function beepOk () {
  tono(1320, 0.08)
}

// Grave y doble: no existe o no hay stock.
export function beepError () {
  tono(220, 0.14)
  tono(220, 0.14, 0.18)
}
