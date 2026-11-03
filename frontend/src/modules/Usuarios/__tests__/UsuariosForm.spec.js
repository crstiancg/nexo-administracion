import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'

const UsuarioService = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/services/UsuarioService', () => ({ default: UsuarioService }))
const RolService = vi.hoisted(() => ({ getData: vi.fn() }))
vi.mock('@/services/RolService', () => ({ default: RolService }))
const PermisoService = vi.hoisted(() => ({ getData: vi.fn() }))
vi.mock('@/services/PermisoService', () => ({ default: PermisoService }))
// El form lista las sedes para asignar; sin mock arrastra boot/axios.
const SedeService = vi.hoisted(() => ({ getData: vi.fn().mockResolvedValue({ data: [] }) }))
vi.mock('@/services/SedeService', () => ({ default: SedeService }))

const useForm = vi.hoisted(() => vi.fn())
vi.mock('laravel-precognition-vue', () => ({ useForm }))

import UsuariosForm from '@/modules/Usuarios/UsuariosForm.vue'

const PERMISOS = [
  { id: 1, name: 'admin-roles', description: 'Administrar roles' },
  { id: 2, name: 'ver-ventas', description: 'Ver ventas' },
  { id: 3, name: 'ver-reportes', description: 'Ver reportes' }
]
const ROLES = [
  { id: 10, name: 'Administrador', permissions: [PERMISOS[0]] },
  { id: 20, name: 'Cajero', permissions: [PERMISOS[1], PERMISOS[2]] }
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
  UsuarioService.get.mockReset()
  RolService.getData.mockReset().mockResolvedValue({ data: ROLES })
  PermisoService.getData.mockReset().mockResolvedValue({ data: PERMISOS })
  useForm.mockReset()
  useForm.mockImplementation((method, url, inputs) => fakeForm(typeof inputs === 'function' ? inputs() : inputs))
})

async function montar (props = {}) {
  const wrapper = mount(UsuariosForm, { props })
  await flushPromises()
  return wrapper
}

describe('UsuariosForm', () => {
  it('sin id crea: POST con el payload anidado y trae roles y permisos sin paginar', async () => {
    await montar()

    const [method, url, inputs] = useForm.mock.calls[0]
    expect([method, url]).toEqual(['post', 'api/usuarios'])
    expect(inputs()).toEqual({
      usuario: { name: '', username: '', email: '', password: '', sede_id: null, rolesSelected: [], permisosSelected: [] }
    })
    expect(RolService.getData).toHaveBeenCalledWith({ params: { rowsPerPage: 0, order_by: 'name' } })
    expect(PermisoService.getData).toHaveBeenCalledWith({ params: { rowsPerPage: 0, order_by: 'name' } })
  })

  it('con id edita: PUT, precarga todo y deja la contraseña vacía', async () => {
    UsuarioService.get.mockResolvedValue({
      user: { id: 5, name: 'Ana', username: 'aperez', email: null },
      rolesSelected: [20],
      permisosSelected: [1]
    })

    const wrapper = await montar({ id: 5 })

    expect(useForm.mock.calls[0].slice(0, 2)).toEqual(['put', 'api/usuarios/5'])
    expect(wrapper.vm.form.setData).toHaveBeenCalledWith({
      usuario: { name: 'Ana', username: 'aperez', email: '', password: '', rolesSelected: [20], permisosSelected: [1] }
    })
    expect(wrapper.text()).toContain('Dejala vacía para no cambiarla')
  })

  it('lista los roles para tildar', async () => {
    const wrapper = await montar()

    const roles = wrapper.findAll('.usuario-roles__item')
    expect(roles.map((r) => r.text())).toEqual(['Administrador', 'Cajero'])
  })

  it('muestra los permisos heredados de los roles tildados', async () => {
    const wrapper = await montar()
    expect(wrapper.find('.usuario-heredados__empty').exists()).toBe(true)

    wrapper.vm.form.usuario.rolesSelected = [20]
    await flushPromises()

    const grupo = wrapper.find('.usuario-heredados__rol')
    expect(grupo.text()).toContain('Cajero')
    expect(grupo.text()).toContain('Ver ventas')
    expect(grupo.text()).toContain('Ver reportes')
    // Sólo los roles tildados: Administrador no está, su permiso tampoco.
    expect(wrapper.findAll('.usuario-heredados__rol')).toHaveLength(1)
    expect(grupo.text()).not.toContain('admin-roles')
  })

  it('marca los heredados que además están asignados de forma directa', async () => {
    const wrapper = await montar()

    wrapper.vm.form.usuario.rolesSelected = [20]
    wrapper.vm.form.usuario.permisosSelected = [2]
    await flushPromises()

    const directos = wrapper.findAll('.usuario-heredados__permiso--directo')
    expect(directos).toHaveLength(1)
    expect(directos[0].text()).toContain('Ver ventas')
  })

  it('submit envía, resetea y emite save', async () => {
    const wrapper = await montar()

    await wrapper.vm.submit()

    expect(wrapper.vm.form.submit).toHaveBeenCalled()
    expect(wrapper.vm.form.reset).toHaveBeenCalled()
    expect(wrapper.emitted('save')).toHaveLength(1)
  })
})
