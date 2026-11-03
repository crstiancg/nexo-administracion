import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppFilterBar from '@/components/AppFilterBar.vue'

function mountBar (props = {}, slots = {}) {
  return mount(AppFilterBar, { props, slots })
}

describe('AppFilterBar — buscador + filtros de quien la usa', () => {
  it('el placeholder del buscador es configurable', () => {
    const wrapper = mountBar({ searchPlaceholder: 'Filtrar por N° o cliente' })
    expect(wrapper.find('input').attributes('placeholder')).toBe('Filtrar por N° o cliente')
  })

  it('renderiza lo que le pasen por el slot default', () => {
    const wrapper = mountBar({}, { default: '<div class="fake-pill">Estado</div>' })
    expect(wrapper.find('.fake-pill').exists()).toBe(true)
  })

  it('sin filtros activos no muestra "Limpiar filtros"', () => {
    expect(mountBar({ hasActiveFilters: false }).find('.app-filter-bar__clear').exists()).toBe(false)
  })

  it('con filtros activos sí lo muestra, y emite clear al clickearlo', async () => {
    const wrapper = mountBar({ hasActiveFilters: true })
    const clear = wrapper.find('.app-filter-bar__clear')

    expect(clear.exists()).toBe(true)
    await clear.trigger('click')
    expect(wrapper.emitted('clear')).toHaveLength(1)
  })
})
