import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { Cookies } from 'quasar'

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('@/boot/axios', () => ({ api }))

import { useUserStore, AuthError, CLIENT_ID } from '@/stores/user-store'

const TOKEN_OK = { data: { token_type: 'Bearer', access_token: 'tok-123', refresh_token: 'ref-456' } }
const USER_OK = {
  data: {
    user: { id: 2, name: 'Usuario de Prueba', username: 'password', email: 'password@gmail.com' },
    roles: [],
    permisos: []
  }
}

function errorHttp (status, data) {
  return Object.assign(new Error(`HTTP ${status}`), { response: { status, data } })
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.stubEnv('VITE_APP_SECRET', 'secret-del-front')
  api.get.mockReset()
  api.post.mockReset()
  Cookies.remove('token', { path: '/' })
})

afterEach(() => {
  vi.unstubAllEnvs()
})

describe('login', () => {
  it('pide el token con el password grant, lo guarda en la cookie y carga el usuario', async () => {
    api.post.mockResolvedValueOnce(TOKEN_OK)
    api.get.mockResolvedValueOnce(USER_OK)
    const store = useUserStore()

    await store.login({ username: 'password@gmail.com', password: 'password' })

    expect(api.post).toHaveBeenCalledWith('oauth/token', {
      grant_type: 'password',
      client_id: CLIENT_ID,
      client_secret: 'secret-del-front',
      username: 'password@gmail.com',
      password: 'password',
      scope: ''
    })
    expect(Cookies.get('token')).toBe('Bearer tok-123')
    expect(api.get).toHaveBeenCalledWith('api/user')
    expect(store.name).toBe('Usuario de Prueba')
    expect(store.username).toBe('password')
    expect(store.email).toBe('password@gmail.com')
    expect(store.initials).toBe('UD')
  })

  it('recorta espacios del usuario antes de enviarlo', async () => {
    api.post.mockResolvedValueOnce(TOKEN_OK)
    api.get.mockResolvedValueOnce(USER_OK)

    await useUserStore().login({ username: '  admin ', password: 'x' })

    expect(api.post.mock.calls[0][1].username).toBe('admin')
  })

  it('credenciales inválidas → AuthError con mensaje para el usuario y sin cookie', async () => {
    api.post.mockRejectedValueOnce(errorHttp(400, { error: 'invalid_grant' }))

    await expect(useUserStore().login({ username: 'a', password: 'mal' }))
      .rejects.toEqual(new AuthError('Usuario o contraseña incorrectos.'))
    expect(Cookies.get('token')).toBeFalsy()
  })

  it('un cliente OAuth mal configurado NO se muestra como credenciales inválidas', async () => {
    api.post.mockRejectedValueOnce(errorHttp(401, { error: 'invalid_client' }))

    const error = await useUserStore().login({ username: 'a', password: 'b' }).catch(e => e)

    expect(error).not.toBeInstanceOf(AuthError)
  })

  it('si falla traer el usuario no queda un token huérfano en la cookie', async () => {
    api.post.mockResolvedValueOnce(TOKEN_OK)
    api.get.mockRejectedValueOnce(errorHttp(500, {}))

    await expect(useUserStore().login({ username: 'a', password: 'b' })).rejects.toThrow()
    expect(Cookies.get('token')).toBeFalsy()
  })
})

describe('logout', () => {
  it('revoca el token en el servidor, borra la cookie y limpia el usuario', async () => {
    api.post.mockResolvedValueOnce(TOKEN_OK).mockResolvedValueOnce({ status: 204 })
    api.get.mockResolvedValueOnce(USER_OK)
    const store = useUserStore()
    await store.login({ username: 'a', password: 'b' })

    await store.logout()

    expect(api.post).toHaveBeenLastCalledWith('api/logout')
    expect(Cookies.get('token')).toBeFalsy()
    expect(store.id).toBeNull()
  })

  it('cierra la sesión local aunque el servidor no responda', async () => {
    Cookies.set('token', 'Bearer tok-123', { path: '/' })
    api.post.mockRejectedValueOnce(new Error('Network Error'))

    await useUserStore().logout()

    expect(Cookies.get('token')).toBeFalsy()
  })
})

describe('hasPermission', () => {
  it('responde según los permisos que devuelve /api/user', async () => {
    api.post.mockResolvedValueOnce(TOKEN_OK)
    api.get.mockResolvedValueOnce({ data: { ...USER_OK.data, permisos: ['ver-usuarios'] } })
    const store = useUserStore()
    await store.login({ username: 'a', password: 'b' })

    expect(store.hasPermission('ver-usuarios')).toBe(true)
    expect(store.hasPermission('crear-usuarios')).toBe(false)
  })
})
