import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'

const PermisoService = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/services/PermisoService', () => ({ default: PermisoService }))

// Doble de useForm: guarda con qué se creó y expone lo que usa el form.
const useForm = vi.hoisted(() => vi.fn())
vi.mock('laravel-precognition-vue', () => ({ useForm }))

import PermisosForm from '@/modules/Permisos/PermisosForm.vue'

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
  PermisoService.get.mockReset().mockResolvedValue({ id: 7, name: 'ventas.index', description: 'Ventas · Ver listado' })
  useForm.mockReset()
  useForm.mockImplementation((method, url, inputs) => fakeForm(typeof inputs === 'function' ? inputs() : inputs))
})

async function montar () {
  const wrapper = mount(PermisosForm, { props: { id: 7 } })
  await flushPromises()
  return wrapper
}

describe('PermisosForm (sólo editar descripción)', () => {
  it('edita con PUT y precarga la descripción', async () => {
    const wrapper = await montar()

    const [method, url, inputs] = useForm.mock.calls[0]
    expect([method, url]).toEqual(['put', 'api/permisos/7'])
    expect(inputs()).toEqual({ permiso: { description: '' } })
    expect(wrapper.vm.form.setData).toHaveBeenCalledWith({ permiso: { description: 'Ventas · Ver listado' } })
  })

  it('muestra el nombre de la ruta como dato, no como campo editable', async () => {
    const wrapper = await montar()

    expect(wrapper.find('.permiso-form__ruta').text()).toContain('ventas.index')
    expect(wrapper.findAll('input')).toHaveLength(1)
  })

  it('muestra el error de validación del backend debajo del campo', async () => {
    const wrapper = await montar()
    wrapper.vm.form.errors['permiso.description'] = 'El campo descripción es obligatorio.'
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('El campo descripción es obligatorio.')
  })

  it('submit envía, resetea y emite save', async () => {
    const wrapper = await montar()

    await wrapper.vm.submit()

    expect(wrapper.vm.form.submit).toHaveBeenCalled()
    expect(wrapper.emitted('save')).toHaveLength(1)
  })

  it('si el backend rechaza, no emite save', async () => {
    const wrapper = await montar()
    wrapper.vm.form.submit.mockRejectedValue({ response: { status: 422 } })

    await wrapper.vm.submit()

    expect(wrapper.emitted('save')).toBeUndefined()
  })
})
