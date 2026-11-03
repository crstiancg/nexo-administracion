import { defineStore } from 'pinia'
import { Cookies } from 'quasar'
import { api } from '@/boot/axios'

/**
 * Cliente del password grant sembrado por ClientTokenSeeder en el backend.
 * Fijo en el código como en muni-asis-sitra; el secret viene del .env.
 */
export const CLIENT_ID = import.meta.env.VITE_APP_CLIENT_ID || 'ebacc5c8-57de-47a5-895a-08daa99ed8de'

/**
 * Error con mensaje listo para la interfaz. Se distingue por clase y no por
 * el texto: un fallo de red o un cliente OAuth mal configurado no es un
 * AuthError y no debe mostrarse como "usuario o contraseña incorrectos".
 */
export class AuthError extends Error {
  constructor (message) {
    super(message)
    this.name = 'AuthError'
  }
}

function initialsOf (name) {
  return (name ?? '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(word => word[0].toUpperCase())
    .join('')
}

export const useUserStore = defineStore('user', {
  state: () => ({
    id: null,
    name: null,
    username: null,
    email: null,
    roles: null,
    permisos: null,
    // { id, nombre, direccion, telefono }: donde opera (POS, caja, stock).
    sede: null
  }),

  getters: {
    getId: (state) => state.id,
    getName: (state) => state.name,
    getEmail: (state) => state.email,
    getRoles: (state) => state.roles,
    getPermisos: (state) => state.permisos,
    initials: (state) => initialsOf(state.name),
    sedeId: (state) => state.sede?.id ?? null
  },

  actions: {
    /**
     * @param {{ username: string, password: string, remember?: boolean }} credentials
     */
    async login ({ username, password, remember = false }) {
      Cookies.remove('token', { path: '/' })

      let res
      try {
        res = await api.post('oauth/token', {
          grant_type: 'password',
          client_id: CLIENT_ID,
          client_secret: import.meta.env.VITE_APP_SECRET,
          username: username.trim(),
          password,
          scope: ''
        })
      } catch (error) {
        // Un solo mensaje para usuario inexistente, inactivo o contraseña
        // equivocada: el backend ya los devuelve igual (invalid_grant) para
        // que el formulario no sirva para averiguar qué usuarios existen.
        if (error.response?.data?.error === 'invalid_grant') {
          throw new AuthError('Usuario o contraseña incorrectos.')
        }
        throw error
      }

      // Sin `expires` la cookie muere al cerrar el navegador; con "mantener
      // la sesión" dura lo mismo que el refresh token del backend.
      Cookies.set('token', `Bearer ${res.data.access_token}`, {
        path: '/',
        sameSite: 'Strict',
        ...(remember ? { expires: 7 } : {})
      })

      try {
        await this.getUser()
      } catch (error) {
        // Sin usuario no hay sesión usable: no dejar un token a medias.
        Cookies.remove('token', { path: '/' })
        throw error
      }
    },

    async getUser () {
      const { data } = await api.get('api/user')
      this.setUser(data)
    },

    /**
     * Revoca el token en el servidor y lo borra localmente. Lo local se borra
     * siempre, aunque el servidor no responda: el usuario pidió salir.
     */
    async logout () {
      try {
        await api.post('api/logout')
      } catch {
        // Sin red igual se cierra la sesión local.
      } finally {
        Cookies.remove('token', { path: '/' })
        this.clearUser()
      }
    },

    setUser (payload) {
      this.id = payload.user.id
      this.name = payload.user.name
      this.username = payload.user.username
      this.email = payload.user.email
      this.roles = payload.roles ?? []
      this.permisos = payload.permisos ?? []
      this.sede = payload.user.sede ?? null
    },

    /**
     * Pasa a operar en otra sede. Quien llama recarga los datos: el stock,
     * la caja y el catálogo cambian con la sede.
     */
    async cambiarSede (sedeId) {
      const { data } = await api.patch('api/user/sede', { sede_id: sedeId })
      this.sede = data
    },

    clearUser () {
      this.$reset()
    },

    hasPermission (permission) {
      return this.permisos?.includes(permission) ?? false
    }
  }
})
