import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

const PermisoService = vi.hoisted(() => ({ getData: vi.fn(), get: vi.fn() }))
vi.mock('@/services/PermisoService', () => ({ default: PermisoService }))
vi.mock('@/modules/Permisos/PermisosCrearForm.vue', () => ({ default: { name: 'PermisosCrearForm', render: () => null } }))
vi.mock('@/boot/axios', () => ({ api: {} }))

const notify = vi.fn()
vi.mock('quasar', async (importOriginal) => ({ ...(await importOriginal()), useQuasar: () => ({ notify }) }))

import PermisosList from '@/modules/Permisos/PermisosList.vue'
import { useUserStore } from '@/stores/user-store'

const PAGINA = {
  data: [
    { id: 2, name: 'roles.store', description: 'Roles · Crear' },
    { id: 1, name: 'roles.index', description: 'Roles · Ver listado' }
  ],
  total: 2
}

function montar (permisos = ['permisos.index', 'permisos.update']) {
  setActivePinia(createPinia())
  useUserStore().permisos = permisos
  // El form va dentro del diálogo y usa Precognition; acá se prueba la lista.
  return mount(PermisosList, { global: { stubs: { PermisosForm: true } } })
}

beforeEach(() => {
  Object.values(PermisoService).forEach((fn) => fn.mockReset())
  notify.mockReset()
  PermisoService.getData.mockResolvedValue(PAGINA)
})

describe('PermisosList', () => {
  it('al montar pide la primera página ordenada por nombre', async () => {
    const wrapper = montar()
    await flushPromises()

    expect(PermisoService.getData).toHaveBeenCalledWith({
      params: { rowsPerPage: 10, page: 1, search: '', order_by: 'name' }
    })
    expect(wrapper.text()).toContain('roles.store')
    expect(wrapper.text()).toContain('Roles · Crear')
  })

  it('buscar vuelve a pedir desde la página 1 con el término', async () => {
    const wrapper = montar()
    await flushPromises()

    await wrapper.find('.app-filter-bar input').setValue('usuarios')
    await new Promise((resolve) => setTimeout(resolve, 450))
    await flushPromises()

    expect(PermisoService.getData).toHaveBeenLastCalledWith({
      params: { rowsPerPage: 10, page: 1, search: 'usuarios', order_by: 'name' }
    })
  })

  it('no ofrece eliminar: un permiso huérfano lo limpia permisos:sync --prune', async () => {
    const wrapper = montar()
    await flushPromises()

    expect(wrapper.find('[aria-label^="Eliminar"]').exists()).toBe(false)
  })

  it('"Nuevo permiso" aparece sólo con permisos.store', async () => {
    const con = montar(['permisos.index', 'permisos.store'])
    await flushPromises()
    expect(con.text()).toContain('Nuevo permiso')

    const sin = montar(['permisos.index'])
    await flushPromises()
    expect(sin.text()).not.toContain('Nuevo permiso')
  })

  it('editar la descripción sólo aparece con permisos.update', async () => {
    const conPermiso = montar()
    await flushPromises()
    expect(conPermiso.find('[aria-label="Editar roles.store"]').exists()).toBe(true)

    const sinPermiso = montar(['permisos.index'])
    await flushPromises()
    expect(sinPermiso.find('[aria-label="Editar roles.store"]').exists()).toBe(false)
  })
})
