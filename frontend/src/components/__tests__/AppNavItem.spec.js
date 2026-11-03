import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppNavItem from '@/components/AppNavItem.vue'

// Stub que expone las props del router-link para poder afirmar qué clases
// de activo le estamos pasando al router.
const RouterLinkStub = {
  name: 'RouterLink',
  props: ['to', 'activeClass', 'exactActiveClass'],
  template: '<a><slot /></a>'
}

function mountItem (props = {}) {
  return mount(AppNavItem, {
    props: { to: '/', label: 'Dashboard', icon: 'dashboard', ...props },
    global: { components: { 'router-link': RouterLinkStub } }
  })
}

describe('AppNavItem — el activo lo decide el router, no una prop', () => {
  it('no expone una prop `active`', () => {
    // Pasar el activo a mano se desincroniza al navegar por código.
    expect(AppNavItem.props).not.toHaveProperty('active')
  })

  it('sin exact pinta también las rutas hijas', () => {
    // /pedidos/123 tiene que seguir marcando "Pedidos".
    const link = mountItem().findComponent(RouterLinkStub)
    expect(link.props('activeClass')).toBe('app-nav-item--active')
  })

  it('con exact sólo pinta el match exacto', () => {
    // La raíz "/" matchea con todo lo de abajo; sin esto queda siempre activa.
    const link = mountItem({ exact: true }).findComponent(RouterLinkStub)
    expect(link.props('activeClass')).toBe('')
    expect(link.props('exactActiveClass')).toBe('app-nav-item--active')
  })
})

describe('AppNavItem — contenido', () => {
  it('renderiza el label', () => {
    expect(mountItem({ label: 'Pedidos' }).text()).toContain('Pedidos')
  })

  it('pasa el destino al router-link', () => {
    expect(mountItem({ to: '/second' }).findComponent(RouterLinkStub).props('to')).toBe('/second')
  })

  it('sin slot badge no renderiza el contenedor del badge', () => {
    expect(mountItem().find('.app-nav-item__badge').exists()).toBe(false)
  })

  it('con slot badge lo renderiza', () => {
    const wrapper = mount(AppNavItem, {
      props: { to: '/x', label: 'Pedidos', icon: 'receipt_long' },
      slots: { badge: '<span>14</span>' },
      global: { components: { 'router-link': RouterLinkStub } }
    })
    expect(wrapper.find('.app-nav-item__badge').text()).toBe('14')
  })
})
