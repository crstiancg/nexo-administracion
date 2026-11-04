import { defineBoot } from '#q-app'
import { iniciarTema } from '@/config/tema'

// Primero que todo: así la primera pantalla ya sale con el color y el modo
// que eligió el usuario, sin un parpadeo del tema por defecto.
export default defineBoot(() => {
  iniciarTema()
})
