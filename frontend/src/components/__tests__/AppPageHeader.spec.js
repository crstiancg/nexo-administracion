import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppPageHeader from '@/components/AppPageHeader.vue'

function mountHeader (props = {}, slots = {}) {
  return mount(AppPageHeader, { props: { title: 'Pedidos', ...props }, slots })
}

describe('AppPageHeader — título e infla-caja', () => {
  it('renderiza el título', () => {
    expect(mountHeader({ title: 'Productos' }).find('h1').text()).toBe('Productos')
  })

  it('sin subtitle no renderiza el párrafo', () => {
    expect(mountHeader().find('.app-page-header__sub').exists()).toBe(false)
  })

  it('con subtitle lo renderiza', () => {
    expect(mountHeader({ subtitle: '8 pedidos en total' }).find('.app-page-header__sub').text()).toBe('8 pedidos en total')
  })

  it('sin slot actions no renderiza el contenedor', () => {
    expect(mountHeader().find('.app-page-header__actions').exists()).toBe(false)
  })

  it('con slot actions lo renderiza', () => {
    const wrapper = mountHeader({}, { actions: '<button>Nuevo</button>' })
    expect(wrapper.find('.app-page-header__actions').text()).toBe('Nuevo')
  })
})
