import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PermisosChecklist from '@/modules/Permisos/PermisosChecklist.vue'

const PERMISOS = [
  { id: 1, name: 'roles.index', description: 'Roles · Ver listado' },
  { id: 2, name: 'roles.store', description: 'Roles · Crear' },
  { id: 3, name: 'usuarios.index', description: 'Usuarios · Ver listado' },
  { id: 4, name: 'usuarios.sesiones.revocar', description: 'Usuarios · Cerrar sesiones' }
]

function montar (modelValue = []) {
  return mount(PermisosChecklist, {
    props: { permisos: PERMISOS, modelValue, 'onUpdate:modelValue': () => {} }
  })
}

describe('PermisosChecklist — agrupado por módulo', () => {
  it('agrupa por el prefijo de la ruta y titula con el nombre del módulo', () => {
    const grupos = montar().findAll('.permisos-check__group')

    expect(grupos.map((g) => g.find('.permisos-check__groupName').text())).toEqual(['Roles', 'Usuarios'])
    expect(grupos[0].findAll('.permisos-check__item')).toHaveLength(2)
    expect(grupos[1].findAll('.permisos-check__item')).toHaveLength(2)
  })

  it('dentro del grupo muestra sólo la acción, sin repetir el módulo', () => {
    const item = montar().findAll('.permisos-check__item')[1]

    expect(item.find('.permisos-check__desc').text()).toBe('Crear')
    expect(item.find('.permisos-check__name').text()).toBe('roles.store')
  })

  it('el contador del grupo refleja cuántos están tildados', () => {
    const grupos = montar([1, 2, 3]).findAll('.permisos-check__group')

    expect(grupos[0].find('.permisos-check__groupCount').text()).toBe('2/2')
    expect(grupos[1].find('.permisos-check__groupCount').text()).toBe('1/2')
  })

  it('tildar el módulo marca todas sus acciones', async () => {
    const wrapper = montar([3])

    await wrapper.findAll('.permisos-check__group')[0].find('[data-test="marcar-grupo"]').trigger('click')

    expect(wrapper.emitted('update:modelValue').at(-1)[0].sort()).toEqual([1, 2, 3])
  })

  it('el buscador filtra y oculta los grupos que quedan vacíos', async () => {
    const wrapper = montar()

    await wrapper.find('.permisos-check__search input').setValue('sesiones')

    const grupos = wrapper.findAll('.permisos-check__group')
    expect(grupos).toHaveLength(1)
    expect(grupos[0].text()).toContain('usuarios.sesiones.revocar')
  })
})
