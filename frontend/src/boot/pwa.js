import { defineBoot } from '#q-app'
import { iniciarPwa } from '@/config/pwa'

// Nexo instalable como app (manifest + public/sw.js).
export default defineBoot(() => {
  iniciarPwa()
})
