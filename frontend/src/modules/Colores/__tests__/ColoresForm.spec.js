import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'

const ColorService = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/services/ColorService', () => ({ default: ColorService }))

const useForm = vi.hoisted(() => vi.fn())
vi.mock('laravel-precognition-vue', () => ({ useForm }))

import ColoresForm from '@/modules/Colores/ColoresForm.vue'

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
  ColorService.get.mockReset()
  useForm.mockReset()
  useForm.mockImplementation((method, url, inputs) => fakeForm(typeof inputs === 'function' ? inputs() : inputs))
})

describe('ColoresForm', () => {
  it('sin id crea: POST con el payload anidado', () => {
    mount(ColoresForm)

    const [method, url, inputs] = useForm.mock.calls[0]
    expect([method, url]).toEqual(['post', 'api/colores'])
    expect(inputs()).toEqual({ color: { nombre: '', hexadecimal: '' } })
  })

  it('con id edita: PUT y precarga', async () => {
    ColorService.get.mockResolvedValue({ id: 3, nombre: 'Rojo', hexadecimal: '#E30613' })

    const wrapper = mount(ColoresForm, { props: { id: 3 } })
    await flushPromises()

    expect(useForm.mock.calls[0].slice(0, 2)).toEqual(['put', 'api/colores/3'])
    expect(wrapper.vm.form.setData).toHaveBeenCalledWith({ color: { nombre: 'Rojo', hexadecimal: '#E30613' } })
  })

  it('el selector nativo escribe el hexadecimal en mayúsculas y lo valida', async () => {
    const wrapper = mount(ColoresForm)

    await wrapper.find('input[type="color"]').setValue('#22c55e')

    expect(wrapper.vm.form.color.hexadecimal).toBe('#22C55E')
    expect(wrapper.vm.form.validate).toHaveBeenCalledWith('color.hexadecimal')
  })

  it('la muestra refleja el hexadecimal tipeado, incluso en formato corto', async () => {
    const wrapper = mount(ColoresForm)

    wrapper.vm.form.color.hexadecimal = 'f0a'
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.color-form__swatch').attributes('style')).toContain('#FF00AA')
  })

  it('con un hexadecimal inválido la muestra queda vacía en vez de mostrar otro color', async () => {
    const wrapper = mount(ColoresForm)

    wrapper.vm.form.color.hexadecimal = '#GG0000'
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.color-form__swatch').classes()).toContain('color-form__swatch--vacio')
  })

  it('muestra el error del backend y submit emite save', async () => {
    const wrapper = mount(ColoresForm)
    wrapper.vm.form.errors['color.hexadecimal'] = 'Ya existe un color con ese hexadecimal.'
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Ya existe un color con ese hexadecimal.')

    await wrapper.vm.submit()
    expect(wrapper.emitted('save')).toHaveLength(1)
  })
})
