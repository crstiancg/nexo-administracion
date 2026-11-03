import { defineBoot } from '#q-app'
import { Cookies, Notify } from 'quasar'
import { useUserStore } from '@/stores/user-store'

/**
 * Las rutas salen de los archivos de src/pages (vue-router/auto-routes), así
 * que en vez de marcar cada página con meta.requiresAuth como en la
 * referencia, todo es privado salvo esta lista.
 */
const PUBLIC_PATHS = ['/login']

export async function authGuard (to) {
  const hasToken = Boolean(Cookies.get('token'))
  const isPublic = PUBLIC_PATHS.includes(to.path)

  if (!hasToken) {
    return isPublic ? undefined : { path: '/login', query: { redirectTo: to.fullPath } }
  }

  const userStore = useUserStore()
  if (!userStore.id) {
    try {
      await userStore.getUser()
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
      message: 'No tenés permiso para acceder a este módulo.',
      position: 'top-right',
      timeout: 3000
    })
    return { path: '/' }
  }
}

export default defineBoot(({ router }) => {
  router.beforeEach(authGuard)
})
