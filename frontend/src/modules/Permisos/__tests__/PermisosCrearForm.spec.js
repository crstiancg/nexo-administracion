import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { reactive } from 'vue'

const PermisoService = vi.hoisted(() => ({ rutasDisponibles: vi.fn() }))
vi.mock('@/services/PermisoService', () => ({ default: PermisoService }))

const useForm = vi.hoisted(() => vi.fn())
vi.mock('laravel-precognition-vue', () => ({ useForm }))

import PermisosCrearForm from '@/modules/Permisos/PermisosCrearForm.vue'

const DISPONIBLES = [
  {
    recurso: 'productos',
    nombre: 'Productos',
    rutas: [
      { name: 'productos.destroy', description: 'Productos · Eliminar', metodo: 'DELETE', uri: 'api/productos/{producto}' },
      { name: 'productos.index', description: 'Productos · Ver listado', metodo: 'GET', uri: 'api/productos' },
      { name: 'productos.store', description: 'Productos · Crear', metodo: 'POST', uri: 'api/productos' }
    ]
  },
  {
    recurso: 'ventas',
    nombre: 'Ventas',
    rutas: [
      { name: 'ventas.index', description: 'Ventas · Ver listado', metodo: 'GET', uri: 'api/ventas' }
    ]
  }
]

function fakeForm (inputs) {
  const form = reactive({
    ...inputs,
    errors: {},
    processing: false,
    validating: false,
    submit: vi.fn(() => Promise.resolve({ data: [{}, {}] })),
    reset: vi.fn()
  })
  return form
}

beforeEach(() => {
  PermisoService.rutasDisponibles.mockReset().mockResolvedValue(DISPONIBLES)
  useForm.mockReset()
  useForm.mockImplementation((method, url, inputs) => fakeForm(typeof inputs === 'function' ? inputs() : inputs))
})

async function montar () {
  const wrapper = mount(PermisosCrearForm)
  await flushPromises()
  return wrapper
}

describe('PermisosCrearForm', () => {
  it('crea con POST a api/permisos eligiendo rutas, no tipeando nombres', async () => {
    const wrapper = await montar()

    const [method, url, inputs] = useForm.mock.calls[0]
    expect([method, url]).toEqual(['post', 'api/permisos'])
    expect(inputs()).toEqual({ permiso: { rutas: [] } })
    expect(wrapper.findAll('input[type="text"]')).toHaveLength(0)
  })

  it('muestra las rutas disponibles agrupadas por recurso con método y URI', async () => {
    const wrapper = await montar()

    const grupos = wrapper.findAll('.permiso-nuevo__recurso')
    expect(grupos.map((g) => g.find('.permiso-nuevo__recursoName').text())).toEqual(['Productos', 'Ventas'])

    const ruta = grupos[0].findAll('.permiso-nuevo__ruta')[0]
    expect(ruta.text()).toContain('DELETE')
    expect(ruta.text()).toContain('api/productos/{producto}')
    expect(ruta.text()).toContain('Eliminar')
  })

  it('tildar el recurso entero elige todas sus rutas', async () => {
    const wrapper = await montar()

    await wrapper.findAll('.permiso-nuevo__recurso')[0].find('[data-test="recurso-completo"]').trigger('click')

    expect(wrapper.vm.form.permiso.rutas).toEqual(['productos.destroy', 'productos.index', 'productos.store'])
  })

  it('también se puede elegir una ruta suelta', async () => {
    const wrapper = await montar()

    await wrapper.findAll('.permiso-nuevo__ruta .q-checkbox')[3].trigger('click')

    expect(wrapper.vm.form.permiso.rutas).toEqual(['ventas.index'])
  })

  it('sin rutas pendientes lo explica en vez de mostrar un form vacío', async () => {
    PermisoService.rutasDisponibles.mockResolvedValue([])
    const wrapper = await montar()

    expect(wrapper.find('.permiso-nuevo__empty').text()).toContain('Todas las rutas ya tienen su permiso')
  })

  it('submit envía y emite save con la cantidad creada', async () => {
    const wrapper = await montar()

    await wrapper.vm.submit()

    expect(wrapper.vm.form.submit).toHaveBeenCalled()
    expect(wrapper.emitted('save')[0]).toEqual([2])
  })
})
