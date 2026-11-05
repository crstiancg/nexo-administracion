import { ref } from 'vue'

/**
 * Nexo como app instalada (PWA). El navegador avisa con `beforeinstallprompt`
 * cuando se puede instalar; se guarda para ofrecerlo desde el menú del
 * usuario ("Instalar Nexo") en vez de esperar a que el usuario lo encuentre.
 */
export const instalable = ref(false)
let aviso = null

export function iniciarPwa () {
  if (!('serviceWorker' in navigator)) return

  navigator.serviceWorker.register('/sw.js').catch(() => {
    // Sin service worker Nexo funciona igual (sólo no se instala).
  })

  window.addEventListener('beforeinstallprompt', (evento) => {
    evento.preventDefault()
    aviso = evento
    instalable.value = true
  })
  window.addEventListener('appinstalled', () => {
    aviso = null
    instalable.value = false
  })
}

export async function instalar () {
  if (!aviso) return
  aviso.prompt()
  await aviso.userChoice
  aviso = null
  instalable.value = false
}
