import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'

const RolService = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/services/RolService', () => ({ default: RolService }))
const PermisoService = vi.hoisted(() => ({ getData: vi.fn() }))
vi.mock('@/services/PermisoService', () => ({ default: PermisoService }))

const useForm = vi.hoisted(() => vi.fn())
vi.mock('laravel-precognition-vue', () => ({ useForm }))

import RolesForm from '@/modules/Roles/RolesForm.vue'

const PERMISOS = [
  { id: 1, name: 'admin-roles', description: 'Administrar roles' },
  { id: 2, name: 'admin-permisos', description: 'Administrar permisos' },
  { id: 3, name: 'ver-ventas', description: 'Ver ventas' }
]

function fakeForm (inputs) {
  const form = reactive({
    ...inputs,
    errors: {},
    processing: false,
    validating: false,
    validate: vi.fn(),
    setData: vi.fn((data) => Object.assign(form, data)),
    submit: vi.fn(() => Promise.resolve({ data: {} })),
    reset: vi.fn()
  })
  return form
}

beforeEach(() => {
  RolService.get.mockReset()
  PermisoService.getData.mockReset()
  PermisoService.getData.mockResolvedValue({ data: PERMISOS })
  useForm.mockReset()
  useForm.mockImplementation((method, url, inputs) => fakeForm(typeof inputs === 'function' ? inputs() : inputs))
})

describe('RolesForm', () => {
  it('sin id crea con el payload anidado y trae todos los permisos sin paginar', async () => {
    const wrapper = mount(RolesForm)
    await flushPromises()

    const [method, url, inputs] = useForm.mock.calls[0]
    expect([method, url]).toEqual(['post', 'api/roles'])
    expect(inputs()).toEqual({ rol: { name: '', permisosSelected: [] } })
    expect(PermisoService.getData).toHaveBeenCalledWith({ params: { rowsPerPage: 0, order_by: 'name' } })
    expect(wrapper.findAll('.permisos-check__item')).toHaveLength(3)
  })

  it('con id edita y precarga nombre y permisos tildados', async () => {
    RolService.get.mockResolvedValue({ rol: { id: 4, name: 'Cajero' }, permisosSelected: [3] })

    const wrapper = mount(RolesForm, { props: { id: 4 } })
    await flushPromises()

    expect(useForm.mock.calls[0].slice(0, 2)).toEqual(['put', 'api/roles/4'])
    expect(wrapper.vm.form.setData).toHaveBeenCalledWith({ rol: { name: 'Cajero', permisosSelected: [3] } })
  })

  it('tildar un permiso lo agrega a permisosSelected', async () => {
    const wrapper = mount(RolesForm)
    await flushPromises()

    await wrapper.findAll('.permisos-check__item input[type="checkbox"], .permisos-check__item .q-checkbox')[0].trigger('click')

    expect(wrapper.vm.form.rol.permisosSelected).toEqual([1])
  })

  it('el buscador filtra por nombre o descripción', async () => {
    const wrapper = mount(RolesForm)
    await flushPromises()

    await wrapper.find('.permisos-check__search input').setValue('ventas')

    const visibles = wrapper.findAll('.permisos-check__item')
    expect(visibles).toHaveLength(1)
    expect(visibles[0].text()).toContain('ver-ventas')
  })

  it('"Marcar todos" tilda sólo los visibles y conserva los ya tildados', async () => {
    RolService.get.mockResolvedValue({ rol: { id: 4, name: 'Cajero' }, permisosSelected: [3] })
    const wrapper = mount(RolesForm, { props: { id: 4 } })
    await flushPromises()

    await wrapper.find('.permisos-check__search input').setValue('admin')
    await wrapper.find('[data-test="marcar-todos"]').trigger('click')

    expect([...wrapper.vm.form.rol.permisosSelected].sort()).toEqual([1, 2, 3])
  })

  it('muestra cuántos permisos hay tildados', async () => {
    RolService.get.mockResolvedValue({ rol: { id: 4, name: 'Cajero' }, permisosSelected: [1, 3] })
    const wrapper = mount(RolesForm, { props: { id: 4 } })
    await flushPromises()

    expect(wrapper.find('.permisos-check__count').text()).toBe('2 de 3')
  })

  it('submit envía y emite save', async () => {
    const wrapper = mount(RolesForm)
    await flushPromises()

    await wrapper.vm.submit()

    expect(wrapper.vm.form.submit).toHaveBeenCalled()
    expect(wrapper.emitted('save')).toHaveLength(1)
  })
})
