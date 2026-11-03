import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

const UsuarioService = vi.hoisted(() => ({ getData: vi.fn(), toggleActive: vi.fn(), delete: vi.fn() }))
vi.mock('@/services/UsuarioService', () => ({ default: UsuarioService }))
vi.mock('@/boot/axios', () => ({ api: {} }))

const notify = vi.fn()
vi.mock('quasar', async (importOriginal) => ({ ...(await importOriginal()), useQuasar: () => ({ notify }) }))

import UsuariosList from '@/modules/Usuarios/UsuariosList.vue'
import { useUserStore } from '@/stores/user-store'

const PAGINA = {
  data: [
    { id: 1, name: 'Yo Admin', username: 'admin', email: null, active: true, roles: [{ id: 10, name: 'Administrador' }] },
    { id: 2, name: 'Ana Pérez', username: 'aperez', email: 'ana@forkids.test', active: true, roles: [{ id: 20, name: 'Cajero' }, { id: 30, name: 'Supervisor' }] },
    { id: 3, name: 'Beto Gómez', username: 'bgomez', email: null, active: false, roles: [] }
  ],
  total: 3
}

let wrapper

const TODOS = ['usuarios.index', 'usuarios.store', 'usuarios.update', 'usuarios.destroy', 'usuarios.toggle-active', 'usuarios.sesiones']

async function montar (permisos = TODOS) {
  useUserStore().permisos = permisos
  wrapper = mount(UsuariosList, {
    global: { stubs: { UsuariosForm: true, SesionesUsuarioDialog: true } },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

const confirmar = async () => {
  await new Promise((resolve) => setTimeout(resolve, 300))
  document.body.querySelector('[data-test="confirmar"]').click()
  await flushPromises()
}

beforeEach(() => {
  setActivePinia(createPinia())
  useUserStore().id = 1
  Object.values(UsuarioService).forEach((fn) => fn.mockReset())
  UsuarioService.getData.mockResolvedValue(structuredClone(PAGINA))
  notify.mockReset()
})

afterEach(() => {
  wrapper?.unmount()
})

describe('UsuariosList', () => {
  it('al montar pide la primera página y muestra usuario, roles y estado', async () => {
    await montar()

    expect(UsuarioService.getData).toHaveBeenCalledWith({
      params: { rowsPerPage: 10, page: 1, search: '', order_by: '-id' }
    })
    const texto = wrapper.text()
    expect(texto).toContain('Ana Pérez')
    expect(texto).toContain('@aperez')
    expect(texto).toContain('Cajero, Supervisor')
    expect(texto).toContain('Sin rol')
    expect(texto).toContain('Inactivo')
  })

  it('dar de baja pide confirmación, llama a la API y actualiza el estado de la fila', async () => {
    await montar()
    UsuarioService.toggleActive.mockResolvedValue({ id: 2, active: false })

    await wrapper.find('[aria-label="Dar de baja a aperez"]').trigger('click')
    expect(UsuarioService.toggleActive).not.toHaveBeenCalled()
    await confirmar()

    expect(UsuarioService.toggleActive).toHaveBeenCalledWith(2)
    expect(wrapper.findAll('tbody tr')[1].text()).toContain('Inactivo')
    expect(notify).toHaveBeenCalled()
  })

  it('a un inactivo se le ofrece activar', async () => {
    await montar()

    expect(wrapper.find('[aria-label="Activar a bgomez"]').exists()).toBe(true)
  })

  it('en tu propia fila no podés darte de baja ni eliminarte', async () => {
    await montar()

    expect(wrapper.find('[aria-label="Dar de baja a admin"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[aria-label="Eliminar a admin"]').attributes('disabled')).toBeDefined()
  })

  it('eliminar pide confirmación, borra y refresca', async () => {
    await montar()
    UsuarioService.delete.mockResolvedValue({})

    await wrapper.find('[aria-label="Eliminar a bgomez"]').trigger('click')
    await confirmar()

    expect(UsuarioService.delete).toHaveBeenCalledWith(3)
    expect(UsuarioService.getData).toHaveBeenCalledTimes(2)
  })

  it('ver sesiones abre el diálogo con ese usuario', async () => {
    await montar()

    await wrapper.find('[aria-label="Ver sesiones de aperez"]').trigger('click')

    const dialogo = wrapper.findComponent({ name: 'SesionesUsuarioDialog' })
    expect(dialogo.props('usuario')).toMatchObject({ id: 2 })
    expect(dialogo.props('modelValue')).toBe(true)
  })

  it('cada acción aparece sólo con su permiso', async () => {
    await montar(['usuarios.index', 'usuarios.sesiones'])

    expect(wrapper.text()).not.toContain('Nuevo usuario')
    expect(wrapper.find('[aria-label="Editar a aperez"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label="Dar de baja a aperez"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label="Eliminar a aperez"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label="Ver sesiones de aperez"]').exists()).toBe(true)
  })
})
