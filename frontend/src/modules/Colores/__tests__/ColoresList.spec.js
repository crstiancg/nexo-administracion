import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

const ColorService = vi.hoisted(() => ({ getData: vi.fn(), get: vi.fn(), delete: vi.fn() }))
vi.mock('@/services/ColorService', () => ({ default: ColorService }))
vi.mock('@/boot/axios', () => ({ api: {} }))

const notify = vi.fn()
vi.mock('quasar', async (importOriginal) => ({ ...(await importOriginal()), useQuasar: () => ({ notify }) }))

import ColoresList from '@/modules/Colores/ColoresList.vue'
import { useUserStore } from '@/stores/user-store'

const PAGINA = {
  data: [
    { id: 2, nombre: 'Azul', hexadecimal: '#1E40AF' },
    { id: 1, nombre: 'Rojo marca', hexadecimal: '#E30613' }
  ],
  total: 2
}

const TODOS = ['colores.index', 'colores.store', 'colores.update', 'colores.destroy']

function montar (permisos = TODOS) {
  setActivePinia(createPinia())
  useUserStore().permisos = permisos
  return mount(ColoresList, { global: { stubs: { ColoresForm: true } } })
}

beforeEach(() => {
  Object.values(ColorService).forEach((fn) => fn.mockReset())
  notify.mockReset()
  ColorService.getData.mockResolvedValue(PAGINA)
})

describe('ColoresList', () => {
  it('al montar pide la primera página y muestra muestra, nombre y hexadecimal', async () => {
    const wrapper = montar()
    await flushPromises()

    expect(ColorService.getData).toHaveBeenCalledWith({
      params: { rowsPerPage: 10, page: 1, search: '', order_by: 'nombre' }
    })
    expect(wrapper.text()).toContain('Rojo marca')
    expect(wrapper.text()).toContain('#E30613')
    expect(wrapper.find('.color-swatch').attributes('style')).toContain('#1E40AF')
  })

  it('eliminar pide confirmación, borra y refresca', async () => {
    const wrapper = montar()
    await flushPromises()
    ColorService.delete.mockResolvedValue({})

    await wrapper.find('[aria-label="Eliminar Azul"]').trigger('click')
    document.body.querySelector('[data-test="confirmar-eliminar"]').click()
    await flushPromises()

    expect(ColorService.delete).toHaveBeenCalledWith(2)
    expect(ColorService.getData).toHaveBeenCalledTimes(2)
  })

  it('cada acción aparece sólo con su permiso', async () => {
    const wrapper = montar(['colores.index'])
    await flushPromises()

    expect(wrapper.text()).not.toContain('Nuevo color')
    expect(wrapper.find('[aria-label="Editar Azul"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label="Eliminar Azul"]').exists()).toBe(false)
  })
})
