import { defineBoot } from '#q-app'
import axios from 'axios'
import { Cookies, Notify } from 'quasar'
import { client } from 'laravel-precognition-vue'
import { axiosAdapter } from 'laravel-precognition/axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_APP_API_URL,
  timeout: 30000,
  headers: { Accept: 'application/json' }
})

api.interceptors.request.use((config) => {
  const token = Cookies.get('token')
  if (token) config.headers.Authorization = token
  return config
})

// Los forms con useForm() validan contra la API con esta misma instancia, así
// les llegan el baseURL y el token. En precognition v2 es useHttpClient; el
// client.use(api) de sistema-botica es de la v0.x y ya no existe.
client.useHttpClient(axiosAdapter(api))

export default defineBoot(({ app, router }) => {
  api.interceptors.response.use(
    (response) => response,
    (error) => {
      const status = error.response?.status
      // Un 401 de /oauth/token es un cliente mal configurado, no una sesión
      // vencida: ahí no hay nada que cerrar y el login maneja el error.
      const isTokenRequest = error.config?.url?.includes('oauth/token')

      if (status === 401 && !isTokenRequest) {
        Cookies.remove('token', { path: '/' })
        router.replace({ path: '/login', query: { redirectTo: router.currentRoute.value.fullPath } })
      } else if (status === 403) {
        Notify.create({
          type: 'negative',
          message: 'No tiene permisos para realizar esta acción.',
          position: 'top-right',
          timeout: 3000
        })
      }

      return Promise.reject(error)
    }
  )

  app.config.globalProperties.$axios = axios
  app.config.globalProperties.$api = api
})

export { api }
