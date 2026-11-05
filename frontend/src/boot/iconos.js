import { defineBoot } from '#q-app'
import { iconoLucide } from '@/config/iconos'

// Todos los q-icon (los nuestros y los de Quasar) pasan por el mapa de
// Lucide; lo que no está ahí se dibuja como antes.
export default defineBoot(({ app }) => {
  app.config.globalProperties.$q.iconMapFn = iconoLucide
})
