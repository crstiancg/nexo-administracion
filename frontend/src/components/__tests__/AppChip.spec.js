import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { QIcon } from 'quasar'
import AppChip, { STATUS_ICONS } from '@/components/AppChip.vue'

const STATUSES = Object.keys(STATUS_ICONS)

function mountChip (props = {}) {
  return mount(AppChip, { props: { status: 'positive', label: 'Completado', ...props } })
}

describe('AppChip — nunca comunica estado sólo por color', () => {
  it('label es obligatorio', () => {
    // Sin texto, un chip de color no existe para quien no distingue el color.
    expect(AppChip.props.label.required).toBe(true)
  })

  it('el ícono lo decide el componente, no el llamador', () => {
    // Si el ícono fuera una prop, se podría olvidar. Acá no hay forma.
    expect(AppChip.props).not.toHaveProperty('icon')
  })

  it.each(STATUSES)('el estado %s trae su propio ícono', (status) => {
    const wrapper = mountChip({ status })
    expect(wrapper.findComponent(QIcon).props('name')).toBe(STATUS_ICONS[status])
  })

  it('cada estado tiene un ícono distinto', () => {
    const icons = Object.values(STATUS_ICONS)
    expect(new Set(icons).size).toBe(icons.length)
  })
})

describe('AppChip — variantes', () => {
  it.each(STATUSES)('el estado %s aplica su clase', (status) => {
    expect(mountChip({ status }).classes()).toContain(`app-chip--${status}`)
  })

  it('rechaza un estado inventado', () => {
    const { validator } = AppChip.props.status
    expect(validator('danger')).toBe(false)
    expect(validator('negative')).toBe(true)
  })

  it('renderiza el label', () => {
    expect(mountChip({ label: 'En proceso' }).text()).toContain('En proceso')
  })
})
