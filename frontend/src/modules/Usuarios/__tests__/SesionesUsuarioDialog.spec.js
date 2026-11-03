import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

const UsuarioService = vi.hoisted(() => ({ getSesiones: vi.fn(), revocarSesion: vi.fn() }))
vi.mock('@/services/UsuarioService', () => ({ default: UsuarioService }))
vi.mock('@/boot/axios', () => ({ api: {} }))

const notify = vi.fn()
vi.mock('quasar', async (importOriginal) => ({ ...(await importOriginal()), useQuasar: () => ({ notify }) }))

import SesionesUsuarioDialog from '@/modules/Usuarios/SesionesUsuarioDialog.vue'
import { useUserStore } from '@/stores/user-store'

const USUARIO = { id: 5, name: 'Ana Pérez', username: 'aperez' }
const SESIONES = [
  { id: 'tok-a', created_at: '2026-09-28T10:00:00Z', expires_at: '2026-09-28T18:00:00Z' },
  { id: 'tok-b', created_at: '2026-09-28T09:00:00Z', expires_at: '2026-09-28T17:00:00Z' }
]

let wrapper

async function abrir (permisos = ['usuarios.sesiones', 'usuarios.sesiones.revocar']) {
  setActivePinia(createPinia())
  useUserStore().permisos = permisos
  wrapper = mount(SesionesUsuarioDialog, {
    props: { modelValue: true, usuario: USUARIO },
    attachTo: document.body
  })
  await new Promise((resolve) => setTimeout(resolve, 300))
  await flushPromises()
}

const $ = (selector) => document.body.querySelector(selector)
const $$ = (selector) => [...document.body.querySelectorAll(selector)]

beforeEach(() => {
  UsuarioService.getSesiones.mockReset().mockResolvedValue(SESIONES)
  UsuarioService.revocarSesion.mockReset().mockResolvedValue({})
  notify.mockReset()
})

afterEach(() => {
  wrapper?.unmount()
})

describe('SesionesUsuarioDialog', () => {
  it('al abrir trae y lista las sesiones activas del usuario', async () => {
    await abrir()

    expect(UsuarioService.getSesiones).toHaveBeenCalledWith(5)
    expect($$('.sesiones__item')).toHaveLength(2)
    expect($('.app-dialog').textContent).toContain('Ana Pérez')
  })

  it('cerrar una sesión la revoca en la API y la saca de la lista', async () => {
    await abrir()

    $('[aria-label="Cerrar sesión tok-a"]').click()
    await flushPromises()

    expect(UsuarioService.revocarSesion).toHaveBeenCalledWith(5, 'tok-a')
    expect($$('.sesiones__item')).toHaveLength(1)
  })

  it('"Cerrar todas" revoca cada una', async () => {
    await abrir()

    $('[data-test="cerrar-todas"]').click()
    await flushPromises()

    expect(UsuarioService.revocarSesion).toHaveBeenCalledTimes(2)
    expect($('.sesiones__empty')).not.toBeNull()
  })

  it('sin sesiones muestra el estado vacío y no ofrece "Cerrar todas"', async () => {
    UsuarioService.getSesiones.mockResolvedValue([])
    await abrir()

    expect($('.sesiones__empty').textContent).toContain('Sin sesiones activas')
    expect($('[data-test="cerrar-todas"]')).toBeNull()
  })

  it('sin usuarios.sesiones.revocar sólo se ven, no se pueden cerrar', async () => {
    await abrir(['usuarios.sesiones'])

    expect($$('.sesiones__item')).toHaveLength(2)
    expect($('[aria-label="Cerrar sesión tok-a"]')).toBeNull()
    expect($('[data-test="cerrar-todas"]')).toBeNull()
  })
})
