import { defineBoot } from '#q-app'
import { Cookies, Notify } from 'quasar'
import { aplicarConfiguracion } from '@/config/tienda'
import ConfiguracionService from '@/services/ConfiguracionService'
import { useUserStore } from '@/stores/user-store'

/**
 * Las rutas salen de los archivos de src/pages (vue-router/auto-routes), así
 * que en vez de marcar cada página con meta.requiresAuth como en la
 * referencia, todo es privado salvo esta lista.
 */
const PUBLIC_PATHS = ['/login']

// Abiertas a cualquiera, con o sin sesión (el seguimiento que abre el QR del
// ticket de una orden de servicio).
const ABIERTAS = ['/seguimiento/']

export async function authGuard (to) {
  if (ABIERTAS.some((prefijo) => to.path.startsWith(prefijo))) return

  const hasToken = Boolean(Cookies.get('token'))
  const isPublic = PUBLIC_PATHS.includes(to.path)

  if (!hasToken) {
    return isPublic ? undefined : { path: '/login', query: { redirectTo: to.fullPath } }
  }

  const userStore = useUserStore()
  if (!userStore.id) {
    try {
      await userStore.getUser()
      // Sin la configuración se sigue con los valores por defecto.
      ConfiguracionService.get().then(aplicarConfiguracion).catch(() => {})
    } catch {
      Cookies.remove('token', { path: '/' })
      return isPublic ? undefined : { path: '/login', query: { redirectTo: to.fullPath } }
    }
  }

  if (to.path === '/login') return { path: '/' }

  // Equivalente al permisosGuard de la referencia: cada página declara el
  // permiso que exige con definePage({ meta: { permiso } }). Esconder la
  // pantalla es comodidad; la autorización real la hace la API con 403.
  const permiso = to.meta?.permiso
  if (permiso && !userStore.hasPermission(permiso)) {
    Notify.create({
      type: 'negative',
      message: 'No tienes permiso para acceder a este módulo.',
      position: 'top-right',
      timeout: 3000
    })
    return { path: '/' }
  }
}

export default defineBoot(({ router }) => {
  router.beforeEach(authGuard)
})
