import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AppBadge, { VARIANTS } from '@/components/AppBadge.vue'

afterEach(() => {
  vi.restoreAllMocks()
})

describe('AppBadge — el punto tampoco puede ser sólo color', () => {
  it('en modo dot renderiza el texto para lector de pantalla', () => {
    const wrapper = mount(AppBadge, { props: { dot: true, srLabel: 'Sin leer' } })
    expect(wrapper.find('.app-badge__sr').text()).toBe('Sin leer')
  })

  it('en modo dot NO renderiza el slot: el punto no lleva cifra', () => {
    const wrapper = mount(AppBadge, {
      props: { dot: true, srLabel: 'Sin leer' },
      slots: { default: '14' }
    })
    expect(wrapper.text()).not.toContain('14')
  })

  it('avisa por consola si el dot va sin srLabel', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    mount(AppBadge, { props: { dot: true } })
    expect(warn).toHaveBeenCalledOnce()
    expect(warn.mock.calls[0][0]).toContain('srLabel')
  })

  it('no avisa cuando el dot trae srLabel', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    mount(AppBadge, { props: { dot: true, srLabel: 'Sin leer' } })
    expect(warn).not.toHaveBeenCalled()
  })

  it('no avisa cuando no es dot', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    mount(AppBadge, { slots: { default: '14' } })
    expect(warn).not.toHaveBeenCalled()
  })
})

describe('AppBadge — variantes', () => {
  it('el default es brand', () => {
    expect(AppBadge.props.variant.default).toBe('brand')
    expect(mount(AppBadge).classes()).toContain('app-badge--brand')
  })

  it.each(VARIANTS)('la variante %s aplica su clase', (variant) => {
    expect(mount(AppBadge, { props: { variant } }).classes()).toContain(`app-badge--${variant}`)
  })

  it('sin dot renderiza el slot con la cifra', () => {
    expect(mount(AppBadge, { slots: { default: '14' } }).text()).toContain('14')
  })

  it('sin dot no aplica la clase de dot', () => {
    expect(mount(AppBadge, { slots: { default: '14' } }).classes()).not.toContain('app-badge--dot')
  })
})
