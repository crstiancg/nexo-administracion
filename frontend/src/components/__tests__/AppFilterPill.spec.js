import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppFilterPill from '@/components/AppFilterPill.vue'

const OPTIONS = [
  { label: 'Todos', value: null },
  { label: 'Completado', value: 'Completado' },
  { label: 'Pendiente', value: 'Pendiente' }
]

function mountPill (props = {}) {
  return mount(AppFilterPill, {
    props: { label: 'Estado', options: OPTIONS, modelValue: null, ...props },
    // QBtnDropdown telepora el QMenu a <body> y sólo lo renderiza una vez
    // abierto: sin adjuntar al DOM real no hay dónde buscar los <q-item>.
    attachTo: document.body
  })
}

describe('AppFilterPill — el label muestra la opción elegida', () => {
  it('sin selección muestra la primera opción (la de "sin filtro")', () => {
    expect(mountPill().find('.app-filter-pill__value').text()).toBe('Todos')
  })

  it('con selección muestra el label de esa opción, no el value', () => {
    expect(mountPill({ modelValue: 'Pendiente' }).find('.app-filter-pill__value').text()).toBe('Pendiente')
  })

  it('el label fijo identifica qué se está filtrando', () => {
    expect(mountPill({ label: 'Canal' }).find('.app-filter-pill__label').text()).toBe('Canal')
  })

  it('clickear una opción emite update:modelValue con su value', async () => {
    const wrapper = mountPill()

    await wrapper.find('.app-filter-pill').trigger('click')
    await new Promise((resolve) => setTimeout(resolve, 300)) // transición de QMenu al abrir

    const items = document.querySelectorAll('.q-item')
    expect(items.length).toBeGreaterThan(0)

    items[1].dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }))
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['Completado'])

    wrapper.unmount()
  })
})
