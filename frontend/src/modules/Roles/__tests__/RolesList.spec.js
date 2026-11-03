import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

const RolService = vi.hoisted(() => ({ getData: vi.fn(), get: vi.fn(), delete: vi.fn() }))
vi.mock('@/services/RolService', () => ({ default: RolService }))
vi.mock('@/boot/axios', () => ({ api: {} }))

const notify = vi.fn()
vi.mock('quasar', async (importOriginal) => ({ ...(await importOriginal()), useQuasar: () => ({ notify }) }))

import RolesList from '@/modules/Roles/RolesList.vue'
import { useUserStore } from '@/stores/user-store'

const PAGINA = {
  data: [
    { id: 2, name: 'Cajero', permissions: [{ id: 3, name: 'ver-ventas' }] },
    { id: 1, name: 'Administrador', permissions: [{ id: 1, name: 'admin-roles' }, { id: 2, name: 'admin-permisos' }] }
  ],
  total: 2
}

const TODOS = ['roles.index', 'roles.store', 'roles.update', 'roles.destroy']

function montar (permisos = TODOS) {
  setActivePinia(createPinia())
  useUserStore().permisos = permisos
  return mount(RolesList, { global: { stubs: { RolesForm: true } } })
}

beforeEach(() => {
  Object.values(RolService).forEach((fn) => fn.mockReset())
  notify.mockReset()
  RolService.getData.mockResolvedValue(PAGINA)
})

describe('RolesList', () => {
  it('al montar pide la primera página y muestra cuántos permisos tiene cada rol', async () => {
    const wrapper = montar()
    await flushPromises()

    expect(RolService.getData).toHaveBeenCalledWith({
      params: { rowsPerPage: 10, page: 1, search: '', order_by: '-id' }
    })
    expect(wrapper.text()).toContain('Administrador')
    expect(wrapper.text()).toContain('2 permisos')
    expect(wrapper.text()).toContain('1 permiso')
  })

  it('eliminar pide confirmación, borra y refresca', async () => {
    const wrapper = montar()
    await flushPromises()
    RolService.delete.mockResolvedValue({})

    await wrapper.find('[aria-label="Eliminar Cajero"]').trigger('click')
    document.body.querySelector('[data-test="confirmar-eliminar"]').click()
    await flushPromises()

    expect(RolService.delete).toHaveBeenCalledWith(2)
    expect(RolService.getData).toHaveBeenCalledTimes(2)
  })

  it('cada acción aparece sólo con su permiso', async () => {
    const wrapper = montar(['roles.index'])
    await flushPromises()

    expect(wrapper.text()).not.toContain('Nuevo rol')
    expect(wrapper.find('[aria-label="Editar Cajero"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label="Eliminar Cajero"]').exists()).toBe(false)
  })
})
